<?php

namespace App\Services;

use App\Models\ContentPublicationAttempt;
use App\Models\PublishingSetting;
use App\Models\SeoAuditLog;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ContentPublishingService
{
    public function __construct(
        protected WixPublishingService $wix,
        protected PublicationRetryPolicy $retryPolicy,
    ) {}

    /**
     * Automatically deliver a newly generated article using the user's
     * configured priority and delivery behavior.
     *
     * @return array{
     *     attempted: array<int, string>,
     *     succeeded: array<int, string>,
     *     failed: array<string, string>
     * }
     */
    public function publishAutomatically(SeoContentDraft $draft): array
    {
        $draft->refresh();

        $settings = PublishingSetting::where('user_id', $draft->user_id)->first();
        $result = [
            'attempted' => [],
            'succeeded' => [],
            'failed' => [],
        ];

        // Automatic delivery is only allowed after the AI review explicitly
        // approves the article. This also makes retries idempotent once a
        // successful channel has marked the article as published.
        if (! $settings || ! $this->retryPolicy->canRun(
            $draft->status,
            (bool) $settings->auto_publish_enabled,
            (bool) $settings->auto_publish_multiple_channels,
        )) {
            return $result;
        }

        $channels = $this->orderedAutomaticChannels($settings, $draft);

        if ($settings->auto_publish_multiple_channels) {
            $successfulChannels = ContentPublicationAttempt::query()
                ->where('seo_content_draft_id', $draft->id)
                ->where('content_version', max(1, (int) $draft->content_version))
                ->where('status', 'succeeded')
                ->pluck('channel')
                ->all();
            $channels = $this->retryPolicy->pendingChannels($channels, $successfulChannels, true);
        }

        foreach ($channels as $channel) {
            $result['attempted'][] = $channel;

            try {
                $this->publish($draft, $channel);
                $result['succeeded'][] = $channel;

                if (! $settings->auto_publish_multiple_channels) {
                    break;
                }
            } catch (Throwable $exception) {
                $result['failed'][$channel] = $exception->getMessage();
            }
        }

        return $result;
    }

    /**
     * @return array{message: string, published_url: ?string, external_id?: ?string, already_delivered?: bool}
     */
    public function publish(SeoContentDraft $draft, string $channel): array
    {
        $draft->loadMissing(['brief', 'site', 'group.site']);
        $draft->site_id ??= $draft->group?->site_id;

        $settings = PublishingSetting::where('user_id', $draft->user_id)->first();

        if (! $settings) {
            throw new RuntimeException('Publishing Settings have not been configured.');
        }

        $version = max(1, (int) $draft->content_version);
        $fingerprint = $this->requestFingerprint($draft, $channel);
        $attempt = ContentPublicationAttempt::firstOrCreate(
            [
                'seo_content_draft_id' => $draft->id,
                'content_version' => $version,
                'channel' => $channel,
            ],
            [
                'user_id' => $draft->user_id,
                'site_id' => $draft->site_id,
                'status' => 'pending',
                'request_fingerprint' => $fingerprint,
            ],
        );

        if ($attempt->status === 'succeeded') {
            return [
                'message' => 'This article version was already delivered through this method.',
                'published_url' => $attempt->external_url,
                'external_id' => $attempt->external_id,
                'already_delivered' => true,
            ];
        }

        $attempt->update([
            'status' => 'processing',
            'request_fingerprint' => $fingerprint,
            'error' => null,
            'attempted_at' => now(),
        ]);

        try {
            $result = match ($channel) {
                'general_webhook' => $this->publishToGeneralWebhook($draft, $settings),
                'wordpress_webhook' => $this->publishToWordPressWebhook($draft, $settings),
                'wordpress_email' => $this->publishToWordPressEmail($draft, $settings),
                'wix' => $this->publishToWix($draft),
                default => throw new RuntimeException('Unknown publishing method.'),
            };

            $attempt->update([
                'status' => 'succeeded',
                'external_id' => $result['external_id'] ?? null,
                'external_url' => $result['published_url'] ?? null,
                'error' => null,
                'succeeded_at' => now(),
            ]);

            $draft->update([
                'status' => 'published',
                'published_url' => $result['published_url'] ?? $draft->published_url,
                'published_at' => now(),
            ]);

            SeoAuditLog::create([
                'user_id' => $draft->user_id,
                'site_id' => $draft->site_id,
                'entity_type' => 'content_publishing',
                'entity_id' => $draft->id,
                'action' => 'content_delivered',
                'message' => $result['message'],
                'context' => [
                    'channel' => $channel,
                    'content_version' => $version,
                    'published_url' => $result['published_url'],
                ],
            ]);

            return $result;
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'failed',
                'error' => str($exception->getMessage())->limit(5000, ''),
            ]);
            SeoAuditLog::create([
                'user_id' => $draft->user_id,
                'site_id' => $draft->site_id,
                'entity_type' => 'content_publishing',
                'entity_id' => $draft->id,
                'action' => 'content_delivery_failed',
                'message' => $exception->getMessage(),
                'context' => ['channel' => $channel, 'content_version' => $version],
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function availableChannels(PublishingSetting $settings, ?SeoContentDraft $draft = null): array
    {
        $channels = [];

        if ($settings->general_webhook_enabled && filled($settings->general_webhook_url)) {
            $channels['general_webhook'] = 'General Website Webhook';
        }

        if ($settings->wordpress_webhook_enabled && filled($settings->wordpress_webhook_url)) {
            $channels['wordpress_webhook'] = 'WordPress Webhook';
        }

        if ($settings->wordpress_email_enabled && filled($settings->wordpress_email)) {
            $channels['wordpress_email'] = 'WordPress Post by Email';
        }

        if ($draft?->site_id && SitePublishingConnection::query()
            ->where('user_id', $draft->user_id)
            ->where('site_id', $draft->site_id)
            ->where('provider', 'wix')
            ->where('is_enabled', true)
            ->exists()) {
            $channels['wix'] = 'Wix Blog';
        }

        return $channels;
    }

    /**
     * @return array<int, string>
     */
    public function orderedAutomaticChannels(PublishingSetting $settings, ?SeoContentDraft $draft = null): array
    {
        $priorities = [
            'wordpress_email' => (int) ($settings->wordpress_email_priority ?: 10),
            'wordpress_webhook' => (int) ($settings->wordpress_webhook_priority ?: 20),
            'general_webhook' => (int) ($settings->general_webhook_priority ?: 30),
        ];
        $channels = array_keys(self::availableChannels($settings, $draft));

        if (in_array('wix', $channels, true)) {
            $priorities['wix'] = (int) SitePublishingConnection::query()
                ->where('user_id', $draft->user_id)
                ->where('site_id', $draft->site_id)
                ->where('provider', 'wix')
                ->value('priority') ?: 40;
        }

        usort(
            $channels,
            fn (string $left, string $right): int => [$priorities[$left], $left] <=> [$priorities[$right], $right],
        );

        return $channels;
    }

    /**
     * @return array{message: string, published_url: ?string}
     */
    private function publishToGeneralWebhook(SeoContentDraft $draft, PublishingSetting $settings): array
    {
        if (! $settings->general_webhook_enabled || blank($settings->general_webhook_url)) {
            throw new RuntimeException('The general website webhook is not enabled.');
        }

        $payload = [
            'event' => 'content.ready',
            'sent_at' => now()->toIso8601String(),
            'article' => $this->articlePayload($draft),
        ];

        $response = $this->postWebhook(
            $settings->general_webhook_url,
            $payload,
            $settings->general_webhook_secret,
            $draft,
        );

        return [
            'message' => 'Content sent to the general website webhook.',
            'published_url' => $this->publishedUrlFromResponse($response),
        ];
    }

    /**
     * @return array{message: string, published_url: ?string}
     */
    private function publishToWordPressWebhook(SeoContentDraft $draft, PublishingSetting $settings): array
    {
        if (! $settings->wordpress_webhook_enabled || blank($settings->wordpress_webhook_url)) {
            throw new RuntimeException('The WordPress webhook is not enabled.');
        }

        $payload = [
            'event' => 'wordpress.create_post',
            'post_title' => $draft->title,
            'post_name' => $draft->slug,
            'post_content' => $draft->html,
            'post_status' => $settings->wordpress_post_status,
            'meta_title' => $draft->meta_title,
            'meta_description' => $draft->meta_description,
            'primary_keyword' => $draft->brief?->primary_keyword,
            'language' => $draft->language,
            'source_article_id' => $draft->id,
            'content_version' => max(1, (int) $draft->content_version),
            'site_id' => $draft->site?->id ?? $draft->group?->site?->id,
            'source_url' => $draft->source_url,
            'featured_image' => $this->featuredImagePayload($draft),
        ];

        $response = $this->postWebhook(
            $settings->wordpress_webhook_url,
            $payload,
            $settings->wordpress_webhook_secret,
            $draft,
        );

        return [
            'message' => 'Content sent to the WordPress publishing webhook.',
            'published_url' => $this->publishedUrlFromResponse($response),
        ];
    }

    /**
     * @return array{message: string, published_url: null}
     */
    private function publishToWordPressEmail(SeoContentDraft $draft, PublishingSetting $settings): array
    {
        if (! $settings->wordpress_email_enabled || blank($settings->wordpress_email)) {
            throw new RuntimeException('WordPress post-by-email is not enabled.');
        }

        $html = $draft->featured_image_status === 'ready' && $draft->featured_image_url
            ? '<p><img src="'.e($draft->featured_image_url).'" alt="'.e($draft->featured_image_alt ?: $draft->title).'" /></p>'.$draft->html
            : $draft->html;

        Mail::html($html, function ($message) use ($draft, $settings): void {
            $message
                ->to($settings->wordpress_email)
                ->subject($draft->title);
        });

        return [
            'message' => "Content emailed to {$settings->wordpress_email} for WordPress publishing.",
            'published_url' => null,
        ];
    }

    private function postWebhook(
        string $url,
        array $payload,
        ?string $secret,
        SeoContentDraft $draft,
    ): Response {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Accept' => 'application/json',
            'X-SEOAI-Event' => $payload['event'],
            'X-SEOAI-Idempotency-Key' => 'article-'.$draft->id.'-v'.max(1, (int) $draft->content_version),
        ];

        if (filled($secret)) {
            $headers['X-SEOAI-Signature'] = 'sha256='.hash_hmac('sha256', $json, $secret);
        }

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->withBody($json, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Publishing webhook returned HTTP {$response->status()}: ".str($response->body())->limit(500),
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(SeoContentDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'title' => $draft->title,
            'slug' => $draft->slug,
            'html' => $draft->html,
            'plain_text' => $draft->plain_text ?: strip_tags($draft->html),
            'meta_title' => $draft->meta_title,
            'meta_description' => $draft->meta_description,
            'primary_keyword' => $draft->brief?->primary_keyword,
            'language' => $draft->language,
            'status' => $draft->status,
            'content_version' => max(1, (int) $draft->content_version),
            'site_id' => $draft->site?->id ?? $draft->group?->site?->id,
            'source_url' => $draft->source_url,
            'featured_image' => $this->featuredImagePayload($draft),
            'site' => [
                'id' => $draft->site?->id ?? $draft->group?->site?->id,
                'url' => $draft->site?->site_url ?? $draft->group?->site?->site_url,
                'name' => $draft->site?->name ?? $draft->group?->site?->name,
            ],
            'created_at' => $draft->created_at?->toIso8601String(),
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }

    private function publishedUrlFromResponse(Response $response): ?string
    {
        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        return $data['published_url']
            ?? $data['post_url']
            ?? $data['url']
            ?? $data['link']
            ?? null;
    }

    /**
     * @return array{url: string, alt: string}|null
     */
    private function featuredImagePayload(SeoContentDraft $draft): ?array
    {
        if ($draft->featured_image_status !== 'ready' || blank($draft->featured_image_url)) {
            return null;
        }

        return [
            'url' => $draft->featured_image_url,
            'alt' => $draft->featured_image_alt ?: $draft->title,
        ];
    }

    /**
     * @return array{message: string, published_url: ?string, external_id: string}
     */
    private function publishToWix(SeoContentDraft $draft): array
    {
        $connection = SitePublishingConnection::query()
            ->where('user_id', $draft->user_id)
            ->where('site_id', $draft->site_id)
            ->where('provider', 'wix')
            ->where('is_enabled', true)
            ->first();

        if (! $connection) {
            throw new RuntimeException('Wix publishing is not enabled for this article site.');
        }

        return $this->wix->publish($draft, $connection);
    }

    private function requestFingerprint(SeoContentDraft $draft, string $channel): string
    {
        return hash('sha256', json_encode([
            'draft_id' => $draft->id,
            'version' => max(1, (int) $draft->content_version),
            'channel' => $channel,
            'title' => $draft->title,
            'slug' => $draft->slug,
            'html' => $draft->html,
            'image' => $draft->featured_image_url,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
