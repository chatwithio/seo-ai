<?php

namespace App\Services;

use App\Models\PublishingSetting;
use App\Models\SeoAuditLog;
use App\Models\SeoContentDraft;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ContentPublishingService
{
    /**
     * @return array{message: string, published_url: ?string}
     */
    public function publish(SeoContentDraft $draft, string $channel): array
    {
        $draft->loadMissing(['brief', 'group.site']);

        $settings = PublishingSetting::where('user_id', $draft->user_id)->first();

        if (! $settings) {
            throw new RuntimeException('Publishing Settings have not been configured.');
        }

        try {
            $result = match ($channel) {
                'general_webhook' => $this->publishToGeneralWebhook($draft, $settings),
                'wordpress_webhook' => $this->publishToWordPressWebhook($draft, $settings),
                'wordpress_email' => $this->publishToWordPressEmail($draft, $settings),
                default => throw new RuntimeException('Unknown publishing method.'),
            };

            $draft->update([
                'status' => 'published',
                'published_url' => $result['published_url'] ?? $draft->published_url,
                'published_at' => now(),
            ]);

            SeoAuditLog::create([
                'user_id' => $draft->user_id,
                'site_id' => $draft->group?->site_id,
                'entity_type' => 'content_publishing',
                'entity_id' => $draft->id,
                'action' => 'content_delivered',
                'message' => $result['message'],
                'context' => [
                    'channel' => $channel,
                    'published_url' => $result['published_url'],
                ],
            ]);

            return $result;
        } catch (Throwable $exception) {
            SeoAuditLog::create([
                'user_id' => $draft->user_id,
                'site_id' => $draft->group?->site_id,
                'entity_type' => 'content_publishing',
                'entity_id' => $draft->id,
                'action' => 'content_delivery_failed',
                'message' => $exception->getMessage(),
                'context' => ['channel' => $channel],
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function availableChannels(PublishingSetting $settings): array
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

        Mail::html($draft->html, function ($message) use ($draft, $settings): void {
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
            'X-SEOAI-Idempotency-Key' => 'article-'.$draft->id.'-'.$draft->updated_at?->timestamp,
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
            'site' => [
                'id' => $draft->group?->site?->id,
                'url' => $draft->group?->site?->site_url,
                'name' => $draft->group?->site?->name,
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
}
