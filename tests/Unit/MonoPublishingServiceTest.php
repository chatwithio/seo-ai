<?php

namespace Tests\Unit;

use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use App\Services\MonoPublishingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonoPublishingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_publish_creates_blog_post_successfully(): void
    {
        $siteUrl = 'https://demo-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([
                'sessionName' => 'PHPSESSID',
                'sessionId' => 'test-session-12345',
            ], 200, ['Set-Cookie' => 'PHPSESSID=test-session-12345; path=/']),

            $siteUrl.'/api.php/blog/posts' => Http::response([
                ['id' => 9876],
            ], 200),

            $siteUrl.'/api.php/blog/posts/9876' => Http::response([
                'data' => [
                    [
                        'id' => 9876,
                        'urlKey' => ['en' => 'ultimate-seo-guide'],
                    ],
                ],
            ], 200),
        ]);

        $draft = new SeoContentDraft([
            'title' => 'Ultimate SEO Guide',
            'slug' => 'ultimate-seo-guide',
            'html' => '<h2>Introduction</h2><p>Learn SEO today.</p>',
            'meta_description' => 'A comprehensive SEO guide for beginners.',
            'language' => 'en',
            'featured_image_status' => 'ready',
            'featured_image_url' => 'https://images.example.com/cover.jpg',
            'featured_image_alt' => 'SEO Guide Cover',
        ]);
        $draft->id = 1;
        $draft->user_id = 10;
        $draft->site_id = 20;

        $connection = new SitePublishingConnection([
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => [
                'username' => 'editor_user',
                'password' => 'secret_pass',
            ],
            'settings' => [
                'site_url' => $siteUrl,
                'author_name' => 'John Editor',
                'post_status' => 'publish',
            ],
        ]);
        $connection->id = 1;
        $connection->user_id = 10;
        $connection->site_id = 20;

        $service = new MonoPublishingService;
        $result = $service->publish($draft, $connection);

        $this->assertSame('9876', $result['external_id']);
        $this->assertSame('https://demo-site.monosolutions.com/blog/ultimate-seo-guide', $result['published_url']);
        $this->assertSame('Article published to Mono blog.', $result['message']);

        Http::assertSent(function ($request) use ($siteUrl) {
            if ($request->url() === $siteUrl.'/api.php/blog/posts' && $request->method() === 'POST') {
                return isset($request['title']['en'])
                    && $request['title']['en'] === 'Ultimate SEO Guide'
                    && isset($request['content']['en'])
                    && str_contains($request['content']['en'], 'Learn SEO today.')
                    && $request['active'] === true
                    && isset($request['featureImage']['src'])
                    && $request['featureImage']['src'] === 'https://images.example.com/cover.jpg';
            }
            return true;
        });
    }

    public function test_test_connection_verifies_blog_access(): void
    {
        $siteUrl = 'https://demo-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([], 200, [
                'Set-Cookie' => 'PHPSESSID=session-abc; path=/',
            ]),
            $siteUrl.'/api.php/blog/posts?perPage=1' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $connection = new SitePublishingConnection([
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => [
                'username' => 'admin_user',
                'password' => 'pass123',
            ],
            'settings' => [
                'site_url' => $siteUrl,
            ],
        ]);
        $connection->id = 5;
        $connection->user_id = 1;
        $connection->site_id = 1;

        $service = new MonoPublishingService;
        $message = $service->testConnection($connection);

        $this->assertStringContainsString('verified', $message);
    }
}
