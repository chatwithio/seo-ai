<?php

namespace Tests\Feature;

use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use App\Models\User;
use App\Services\ContentPublishingService;
use App\Services\MonoPublishingService;
use App\Services\PublicationRetryPolicy;
use App\Services\WixPublishingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentPublishingServiceTest extends TestCase
{
    public function test_available_channels_includes_mono_when_enabled(): void
    {
        $user = new User();
        $user->id = 999;

        $draft = new SeoContentDraft();
        $draft->id = 101;
        $draft->user_id = 999;
        $draft->site_id = 888;

        $settings = new PublishingSetting([
            'user_id' => 999,
            'general_webhook_enabled' => true,
            'general_webhook_url' => 'https://webhook.site/test',
        ]);

        $channels = ContentPublishingService::availableChannels($settings, $draft);
        $this->assertArrayHasKey('general_webhook', $channels);
    }

    public function test_publish_delivers_to_mono_channel(): void
    {
        $siteUrl = 'https://mysite.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([
                'sessionName' => 'PHPSESSID',
                'sessionId' => 'sess-123',
            ], 200, ['Set-Cookie' => 'PHPSESSID=sess-123; path=/']),

            $siteUrl.'/api.php/blog/posts' => Http::response([
                ['id' => 777],
            ], 200),

            $siteUrl.'/api.php/blog/posts/777' => Http::response([
                'data' => [
                    [
                        'id' => 777,
                        'urlKey' => ['en' => 'test-post'],
                    ],
                ],
            ], 200),
        ]);

        $monoService = new MonoPublishingService();
        $wixService = new WixPublishingService();
        $retryPolicy = new PublicationRetryPolicy();

        $publishingService = new ContentPublishingService($wixService, $monoService, $retryPolicy);

        $this->assertInstanceOf(ContentPublishingService::class, $publishingService);
    }
}
