<?php

namespace App\Services;

use App\Models\ContentPublicationAttempt;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Publishes SEO drafts to Mono-powered websites via the Mono SiteAPI.
 *
 * Docs: https://www.monoextranet.com/siteapi/  (spec: https://www.mono.net/api.php/doc)
 *
 * Auth:    POST {site_url}/api.php/login  (form-data: username, password)
 *          → Set-Cookie session, or body {sessionName, sessionId}
 *
 * Create:  POST {site_url}/api.php/blog/posts
 * Update:  PATCH {site_url}/api.php/blog/posts/{id}
 * Fetch:   GET  {site_url}/api.php/blog/posts/{id}
 *
 * Post body fields (all locale-keyed objects):
 *   title, content, preamble, urlKey, seoTitle, seoDescription, seoKeywords
 *   + scalar: active (bool), activeFrom (unix ts), author (string)
 */
class MonoPublishingService
{
    /**
     * @return array{message: string, published_url: ?string, external_id: string}
     */
    public function publish(SeoContentDraft $draft, SitePublishingConnection $connection): array
    {
        $this->assertConnection($draft, $connection);

        $settings      = $connection->settings ?? [];
        $siteUrl       = rtrim((string) ($settings['site_url'] ?? ''), '/');
        $locale        = $this->localeCode($draft->language);
        $shouldPublish = ($settings['post_status'] ?? 'publish') === 'publish';

        Log::info('[MonoPublishing] Starting publish', [
            'draft_id' => $draft->id,
            'title'    => $draft->title,
            'site_url' => $siteUrl,
            'locale'   => $locale,
            'active'   => $shouldPublish,
        ]);

        $client = $this->authenticatedClient($connection);

        $slug     = $draft->slug ?: \Illuminate\Support\Str::slug($draft->title);
        $excerpt  = \Illuminate\Support\Str::limit(strip_tags((string) ($draft->meta_description ?: '')), 500, '');
        $keywords = ''; // focus_keyword not tracked on draft model

        $postPayload = [
            'title'          => [$locale => $draft->title],
            'content'        => [$locale => $draft->html],
            'preamble'       => [$locale => $excerpt],
            'urlKey'         => [$locale => $slug],
            'seoTitle'       => [$locale => $draft->title],
            'seoDescription' => [$locale => $excerpt],
            'seoKeywords'    => [$locale => $keywords],
            'active'         => $shouldPublish,
            'activeFrom'     => now()->timestamp,
            'author'         => (string) ($settings['author_name'] ?? ''),
        ];

        if ($draft->featured_image_status === 'ready' && filled($draft->featured_image_url)) {
            $postPayload['featureImage'] = [
                'src' => $draft->featured_image_url,
                'alt' => [$locale => (string) ($draft->featured_image_alt ?: $draft->title)],
            ];
        }

        // Try to update existing post first, fall back to create
        $previousId = ContentPublicationAttempt::query()
            ->where('seo_content_draft_id', $draft->id)
            ->where('channel', 'mono')
            ->whereNotNull('external_id')
            ->latest('id')
            ->value('external_id');

        $postId = null;

        if ($previousId) {
            try {
                $response = $client->patch($siteUrl.'/api.php/blog/posts/'.rawurlencode($previousId), $postPayload);
                if ($response->successful()) {
                    $postId = (string) $previousId;
                }
            } catch (\Throwable) {
                $postId = null;
            }
        }

        if (! $postId) {
            $response = $client->post($siteUrl.'/api.php/blog/posts', $postPayload);

            if (! $response->successful()) {
                $err = $response->json('error.message')
                    ?? $response->json('message')
                    ?? $response->body();
                throw new RuntimeException("Mono post creation failed (HTTP {$response->status()}): {$err}");
            }

            $responseData = $response->json();
            $postId = (string) (
                $responseData[0]['id']
                ?? $responseData['id']
                ?? $responseData[0]
                ?? ''
            );
        }

        if ($postId === '') {
            throw new RuntimeException('Mono SiteAPI created the post but did not return its ID.');
        }

        // Build public URL — fetch real urlKey from API
        $postUrl = null;
        if ($shouldPublish) {
            try {
                $postResponse = $client->get($siteUrl.'/api.php/blog/posts/'.rawurlencode($postId));
                $postData     = $postResponse->json('data.0') ?? $postResponse->json('data') ?? $postResponse->json();
                $urlKey       = $postData[$locale]['urlKey']
                    ?? $postData['urlKey'][$locale]
                    ?? $slug;
                $postUrl = rtrim($siteUrl, '/').'/blog/'.$urlKey;
            } catch (\Throwable) {
                $postUrl = rtrim($siteUrl, '/').'/blog/'.$slug;
            }
        }

        return [
            'message'       => $shouldPublish ? 'Article published to Mono blog.' : 'Article saved as Mono blog draft.',
            'published_url' => $postUrl,
            'external_id'   => $postId,
        ];
    }

    public function testConnection(SitePublishingConnection $connection): string
    {
        $settings = $connection->settings ?? [];
        $siteUrl  = rtrim((string) ($settings['site_url'] ?? ''), '/');

        if (blank($siteUrl)) {
            throw new RuntimeException('Mono site URL is not configured.');
        }

        $client = $this->authenticatedClient($connection);

        $response = $client->get($siteUrl.'/api.php/blog/posts', ['perPage' => 1]);

        if ($response->status() === 401) {
            throw new RuntimeException('Mono login failed — check your username and password.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Mono SiteAPI connection failed with HTTP '.$response->status().'.');
        }

        $connection->update([
            'last_tested_at'    => now(),
            'last_test_status'  => 'success',
            'last_test_message' => 'Mono SiteAPI blog access verified.',
        ]);

        return 'Mono SiteAPI blog access verified.';
    }

    /**
     * Returns an authenticated HTTP client with session cookie.
     * Session is cached per connection for 4 hours.
     */
    public function authenticatedClient(SitePublishingConnection $connection): PendingRequest
    {
        $credentials = $connection->credentials ?? [];
        $settings    = $connection->settings ?? [];
        $siteUrl     = rtrim((string) ($settings['site_url'] ?? ''), '/');
        $username    = (string) ($credentials['username'] ?? '');
        $password    = (string) ($credentials['password'] ?? '');

        if (blank($siteUrl) || blank($username) || blank($password)) {
            throw new RuntimeException('Mono SiteAPI requires site_url in settings and username/password in credentials.');
        }

        $cacheKey = 'mono_session_'.md5($siteUrl.$username.$connection->id);

        $cookie = Cache::remember($cacheKey, now()->addHours(4), function () use ($siteUrl, $username, $password) {
            return $this->login($siteUrl, $username, $password);
        });

        return Http::connectTimeout(15)
            ->timeout(60)
            ->acceptJson()
            ->withHeaders(['Cookie' => $cookie]);
    }

    private function login(string $siteUrl, string $username, string $password): string
    {
        $response = Http::connectTimeout(15)
            ->timeout(30)
            ->asForm()
            ->post($siteUrl.'/api.php/login', [
                'username' => $username,
                'password' => $password,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Mono login failed (HTTP '.$response->status().'). Check your username and password.'
            );
        }

        // Prefer Set-Cookie header
        $setCookie = $response->header('Set-Cookie');
        if (filled($setCookie)) {
            return trim(explode(';', $setCookie)[0]);
        }

        // Fall back to body {sessionName, sessionId}
        $body        = $response->json();
        $sessionName = $body['sessionName'] ?? 'PHPSESSID';
        $sessionId   = $body['sessionId']   ?? '';

        if (blank($sessionId)) {
            throw new RuntimeException('Mono login succeeded but no session cookie was returned.');
        }

        return "{$sessionName}={$sessionId}";
    }

    private function assertConnection(SeoContentDraft $draft, SitePublishingConnection $connection): void
    {
        if (! $connection->is_enabled || $connection->provider !== 'mono') {
            throw new RuntimeException('Mono publishing is not enabled for this site.');
        }

        if ((int) $connection->user_id !== (int) $draft->user_id
            || (int) $connection->site_id !== (int) $draft->site_id) {
            throw new RuntimeException('This Mono connection does not belong to the article site.');
        }
    }

    private function localeCode(?string $language): string
    {
        return match (strtolower((string) $language)) {
            'danish', 'da'       => 'da',
            'spanish', 'es'      => 'es',
            'french', 'fr'       => 'fr',
            'italian', 'it'      => 'it',
            'german', 'de'       => 'de',
            'portuguese', 'pt'   => 'pt',
            'dutch', 'nl'        => 'nl',
            'swedish', 'sv'      => 'sv',
            'norwegian', 'no'    => 'no',
            'finnish', 'fi'      => 'fi',
            default              => 'en',
        };
    }
}
