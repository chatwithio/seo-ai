<?php

namespace App\Services;

use App\Models\ContentPublicationAttempt;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WixPublishingService
{
    private const BASE_URL = 'https://www.wixapis.com';

    /**
     * @return array{message: string, published_url: ?string, external_id: string}
     */
    public function publish(SeoContentDraft $draft, SitePublishingConnection $connection): array
    {
        $this->assertConnection($draft, $connection);
        $settings = $connection->settings ?? [];
        $html = $draft->html;

        if ($draft->featured_image_status === 'ready' && filled($draft->featured_image_url)) {
            $html = '<p><img src="'.e($draft->featured_image_url).'" alt="'.e($draft->featured_image_alt ?: $draft->title).'" /></p>'.$html;
        }

        if (strlen($html) > 30_000) {
            throw new RuntimeException('Wix Rich Content accepts at most 30,000 HTML characters. Shorten this article before publishing.');
        }

        $document = $this->request($connection)
            ->post(self::BASE_URL.'/ricos/v1/ricos-document/convert/to-ricos', ['html' => $html])
            ->throw()
            ->json('document');

        if (! is_array($document)) {
            throw new RuntimeException('Wix did not return a valid Rich Content document.');
        }

        $currentAttempt = ContentPublicationAttempt::query()
            ->where('seo_content_draft_id', $draft->id)
            ->where('content_version', max(1, (int) $draft->content_version))
            ->where('channel', 'wix')
            ->first();
        $previousId = $currentAttempt?->external_id ?: ContentPublicationAttempt::query()
            ->where('seo_content_draft_id', $draft->id)
            ->where('channel', 'wix')
            ->where('status', 'succeeded')
            ->whereNotNull('external_id')
            ->latest('id')
            ->value('external_id');
        $draftPost = [
            'title' => $draft->title,
            'memberId' => $settings['member_id'] ?? null,
            'richContent' => $document,
            'excerpt' => Str::limit(strip_tags((string) ($draft->meta_description ?: $draft->plain_text)), 500, ''),
            'language' => $this->languageCode($draft->language),
            'commentingEnabled' => true,
        ];

        if ($previousId) {
            $response = $this->request($connection)
                ->patch(self::BASE_URL.'/blog/v3/draft-posts/'.rawurlencode($previousId), [
                    'draftPost' => ['id' => $previousId, ...$draftPost],
                ])
                ->throw();
        } else {
            $response = $this->request($connection)
                ->post(self::BASE_URL.'/blog/v3/draft-posts', [
                    'draftPost' => $draftPost,
                    'publish' => false,
                ])
                ->throw();
        }

        $draftPostId = (string) ($response->json('draftPost.id') ?: $previousId);

        if ($draftPostId === '') {
            throw new RuntimeException('Wix created the draft but did not return its ID.');
        }

        // Persist the draft ID before publishing so a failed publish retry updates
        // the same Wix draft instead of creating another external post.
        $currentAttempt?->update(['external_id' => $draftPostId]);

        $shouldPublish = ($settings['post_status'] ?? 'draft') === 'publish';

        if ($shouldPublish) {
            $published = $this->request($connection)
                ->post(self::BASE_URL.'/blog/v3/draft-posts/'.rawurlencode($draftPostId).'/publish')
                ->throw();
            $publishedUrl = $published->json('post.url')
                ?? $published->json('post.link')
                ?? $published->json('url');
        }

        return [
            'message' => $shouldPublish ? 'Article published to Wix.' : 'Article saved as a Wix draft.',
            'published_url' => $publishedUrl ?? null,
            'external_id' => $draftPostId,
        ];
    }

    public function testConnection(SitePublishingConnection $connection): string
    {
        $ricosResponse = $this->request($connection)
            ->post(self::BASE_URL.'/ricos/v1/ricos-document/convert/to-ricos', [
                'html' => '<p>SEO AI connection test</p>',
            ])
            ->throw();

        if (! is_array($ricosResponse->json('document'))) {
            throw new RuntimeException('Wix Ricos conversion permission is missing or returned an invalid response.');
        }

        $response = $this->request($connection)
            ->get(self::BASE_URL.'/blog/v3/draft-posts', ['paging.limit' => 1])
            ->throw();

        $connection->update([
            'last_tested_at' => now(),
            'last_test_status' => 'success',
            'last_test_message' => 'Wix Blog access and Ricos conversion are working.',
        ]);

        return 'Wix Blog access and Ricos conversion are working (HTTP '.$response->status().').';
    }

    private function request(SitePublishingConnection $connection): PendingRequest
    {
        $credentials = $connection->credentials ?? [];
        $settings = $connection->settings ?? [];

        if (blank($credentials['api_key'] ?? null) || blank($settings['wix_site_id'] ?? null)) {
            throw new RuntimeException('Wix API key and Wix Site ID are required.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->connectTimeout(15)
            ->timeout(60)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $credentials['api_key'],
                'wix-site-id' => $settings['wix_site_id'],
            ]);
    }

    private function assertConnection(SeoContentDraft $draft, SitePublishingConnection $connection): void
    {
        if (! $connection->is_enabled || $connection->provider !== 'wix') {
            throw new RuntimeException('Wix publishing is not enabled for this site.');
        }

        if ((int) $connection->user_id !== (int) $draft->user_id || (int) $connection->site_id !== (int) $draft->site_id) {
            throw new RuntimeException('This Wix connection does not belong to the article site.');
        }

        if (blank(($connection->settings ?? [])['member_id'] ?? null)) {
            throw new RuntimeException('Wix Blog member ID is required.');
        }
    }

    private function languageCode(?string $language): string
    {
        return match (strtolower((string) $language)) {
            'spanish' => 'es',
            'french' => 'fr',
            'italian' => 'it',
            'german' => 'de',
            'portuguese' => 'pt',
            default => 'en',
        };
    }
}
