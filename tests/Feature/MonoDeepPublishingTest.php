<?php

namespace Tests\Feature;

use App\Models\ContentPublicationAttempt;
use App\Models\GscSite;
use App\Models\PublishingSetting;
use App\Models\SeoAuditLog;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use App\Models\User;
use App\Services\ContentPublishingService;
use App\Services\MonoPublishingService;
use App\Services\MonoQuickCreatorService;
use App\Services\PublicationRetryPolicy;
use App\Services\WixPublishingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MonoDeepPublishingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mono_publishing_maps_non_english_languages(): void
    {
        $siteUrl = 'https://spanish-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([], 200, [
                'Set-Cookie' => 'PHPSESSID=session-es-123; path=/',
            ]),
            $siteUrl.'/api.php/blog/posts' => Http::response([['id' => 123]], 200),
            $siteUrl.'/api.php/blog/posts/123' => Http::response([
                'data' => [['id' => 123, 'urlKey' => ['es' => 'guia-seo-es']]],
            ], 200),
        ]);

        $draft = new SeoContentDraft([
            'title' => 'Guía Completa de SEO',
            'slug' => 'guia-seo-es',
            'html' => '<p>Contenido en español.</p>',
            'meta_description' => 'Aprende SEO hoy.',
            'language' => 'Spanish',
        ]);
        $draft->id = 50;
        $draft->user_id = 1;
        $draft->site_id = 2;

        $connection = new SitePublishingConnection([
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => ['username' => 'user_es', 'password' => 'secret'],
            'settings' => ['site_url' => $siteUrl, 'post_status' => 'publish'],
        ]);
        $connection->id = 10;
        $connection->user_id = 1;
        $connection->site_id = 2;

        $service = new MonoPublishingService();
        $result = $service->publish($draft, $connection);

        $this->assertSame('123', $result['external_id']);
        $this->assertSame('https://spanish-site.monosolutions.com/blog/guia-seo-es', $result['published_url']);

        Http::assertSent(function ($request) use ($siteUrl) {
            if ($request->url() === $siteUrl.'/api.php/blog/posts') {
                return isset($request['title']['es'])
                    && $request['title']['es'] === 'Guía Completa de SEO'
                    && isset($request['content']['es']);
            }
            return true;
        });
    }

    public function test_mono_publishing_retries_existing_post_via_patch(): void
    {
        $siteUrl = 'https://site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([], 200, [
                'Set-Cookie' => 'PHPSESSID=session-patch; path=/',
            ]),
            $siteUrl.'/api.php/blog/posts/555' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $site = GscSite::create([
            'user_id' => $user->id,
            'site_url' => 'https://example.com',
            'permission_level' => 'siteOwner',
        ]);

        $draft = SeoContentDraft::create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'title' => 'Updated Post',
            'slug' => 'updated-post',
            'html' => '<p>Updated content.</p>',
            'status' => 'approved',
            'content_version' => 2,
        ]);

        ContentPublicationAttempt::create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'seo_content_draft_id' => $draft->id,
            'content_version' => 1,
            'channel' => 'mono',
            'status' => 'succeeded',
            'external_id' => '555',
            'request_fingerprint' => 'fp-v1',
        ]);

        $connection = SitePublishingConnection::create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => ['username' => 'editor', 'password' => 'secret'],
            'settings' => ['site_url' => $siteUrl, 'post_status' => 'publish'],
        ]);

        $service = new MonoPublishingService();
        $result = $service->publish($draft, $connection);

        $this->assertSame('555', $result['external_id']);
        Http::assertSent(fn ($req) => $req->method() === 'PATCH' && str_contains($req->url(), '/api.php/blog/posts/555'));
        Http::assertNotSent(fn ($req) => $req->method() === 'POST' && str_contains($req->url(), '/api.php/blog/posts'));

        // Clean up test records
        $draft->delete();
        $site->delete();
        $user->delete();
    }

    public function test_mono_publishing_fails_with_clear_error_when_login_is_invalid(): void
    {
        $siteUrl = 'https://locked-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([
                'error' => ['message' => 'Wrong username / password'],
            ], 401),
        ]);

        $draft = new SeoContentDraft([
            'title' => 'Test Article',
            'html' => '<p>Test</p>',
        ]);
        $draft->id = 1;
        $draft->user_id = 1;
        $draft->site_id = 1;

        $connection = new SitePublishingConnection([
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => ['username' => 'wrong_user', 'password' => 'wrong_pass'],
            'settings' => ['site_url' => $siteUrl],
        ]);
        $connection->id = 1;
        $connection->user_id = 1;
        $connection->site_id = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mono login failed (HTTP 401)');

        $service = new MonoPublishingService();
        $service->publish($draft, $connection);
    }

    public function test_mono_publishing_fails_with_descriptive_error_on_validation_failure(): void
    {
        $siteUrl = 'https://valid-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([], 200, [
                'Set-Cookie' => 'PHPSESSID=session-ok; path=/',
            ]),
            $siteUrl.'/api.php/blog/posts' => Http::response([
                'error' => ['message' => 'Missing required field: title'],
            ], 422),
        ]);

        $draft = new SeoContentDraft([
            'title' => '',
            'html' => '<p>Body</p>',
        ]);
        $draft->id = 1;
        $draft->user_id = 1;
        $draft->site_id = 1;

        $connection = new SitePublishingConnection([
            'provider' => 'mono',
            'is_enabled' => true,
            'credentials' => ['username' => 'editor', 'password' => 'secret'],
            'settings' => ['site_url' => $siteUrl],
        ]);
        $connection->id = 1;
        $connection->user_id = 1;
        $connection->site_id = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required field: title');

        $service = new MonoPublishingService();
        $service->publish($draft, $connection);
    }

    public function test_content_publishing_service_end_to_end_updates_draft_and_creates_audit_log(): void
    {
        $siteUrl = 'https://e2e-site.monosolutions.com';

        Http::fake([
            $siteUrl.'/api.php/login' => Http::response([], 200, [
                'Set-Cookie' => 'PHPSESSID=session-e2e; path=/',
            ]),
            $siteUrl.'/api.php/blog/posts' => Http::response([['id' => 8888]], 200),
            $siteUrl.'/api.php/blog/posts/8888' => Http::response([
                'data' => [['id' => 8888, 'urlKey' => ['en' => 'great-seo-tips']]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $site = GscSite::create([
            'user_id' => $user->id,
            'site_url' => 'https://example.com',
            'permission_level' => 'siteOwner',
        ]);

        PublishingSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['auto_publish_enabled' => true],
        );

        SitePublishingConnection::create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'provider' => 'mono',
            'is_enabled' => true,
            'priority' => 15,
            'credentials' => ['username' => 'e2e_editor', 'password' => 'secret'],
            'settings' => ['site_url' => $siteUrl, 'post_status' => 'publish'],
        ]);

        $draft = SeoContentDraft::create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'title' => 'Great SEO Tips',
            'slug' => 'great-seo-tips',
            'html' => '<p>Tips for ranking high.</p>',
            'status' => 'approved',
            'content_version' => 1,
        ]);

        $publisher = app(ContentPublishingService::class);
        $result = $publisher->publish($draft, 'mono');

        $draft->refresh();

        $this->assertSame('published', $draft->status);
        $this->assertSame('https://e2e-site.monosolutions.com/blog/great-seo-tips', $draft->published_url);
        $this->assertNotNull($draft->published_at);

        $this->assertDatabaseHas('content_publication_attempts', [
            'seo_content_draft_id' => $draft->id,
            'channel' => 'mono',
            'status' => 'succeeded',
            'external_id' => '8888',
        ]);

        $this->assertDatabaseHas('seo_audit_logs', [
            'user_id' => $user->id,
            'site_id' => $site->id,
            'entity_type' => 'content_publishing',
            'entity_id' => $draft->id,
            'action' => 'content_delivered',
        ]);

        // Cleanup
        $draft->delete();
        $site->delete();
        $user->delete();
    }

    public function test_mono_quick_creator_full_site_creation_lifecycle(): void
    {
        Http::fake([
            'https://qc-api.yggdrasil.dev-mono.net/api/v1/generate-content' => Http::response([
                'status' => 'success',
                'data' => [
                    'en' => [
                        'text' => ['headline' => 'AI Generated Business'],
                    ],
                ],
            ], 200),
            'https://qc-api.yggdrasil.dev-mono.net/api/v1/sites' => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'queued',
                    'jobId' => 1700000000000,
                ],
            ], 202),
            'https://qc-api.yggdrasil.dev-mono.net/api/v1/jobs/1700000000000' => Http::response([
                'status' => 'success',
                'data' => [
                    'jobId' => 1700000000000,
                    'status' => 'done',
                    'result' => [
                        'siteId' => 1406986,
                        'previewUrl' => 'https://sandbox.monosolutions.com',
                    ],
                ],
            ], 200),
            'https://qc-api.yggdrasil.dev-mono.net/api/v1/sites/1406986/editor-url' => Http::response([
                'status' => 'success',
                'data' => [
                    'loginUrl' => 'https://editor.monosolutions.com/login?ticket=xyz789',
                ],
            ], 200),
        ]);

        config([
            'services.mono.base_url' => 'https://qc-api.yggdrasil.dev-mono.net/api/v1',
            'services.mono.token' => 'test-bearer-token',
            'services.mono.template_id' => 1378062,
        ]);

        Storage::fake('local');
        $service = app(MonoQuickCreatorService::class);

        // 1. Generate content
        $genResult = $service->generate([
            'company' => 'Acme Inc',
            'business_type' => 'dentist',
            'services' => 'Cleaning, Whitening',
        ]);
        $this->assertArrayHasKey('response', $genResult);

        // 2. Create site
        $siteJob = $service->createSite([
            'templateId' => 1378062,
            'globalData' => [
                'companyName' => 'Acme Inc',
                'siteLanguage' => 'EN',
            ],
        ]);
        $this->assertSame(1700000000000, $siteJob['jobId']);
        $this->assertSame('queued', $siteJob['status']);

        // 3. Track job
        $jobStatus = $service->getJobStatus(1700000000000);
        $this->assertSame('done', $jobStatus['status']);
        $this->assertSame(1406986, $jobStatus['result']['siteId']);

        // 4. Get editor login URL
        $editorUrl = $service->getSiteEditorUrl(1406986);
        $this->assertSame('https://editor.monosolutions.com/login?ticket=xyz789', $editorUrl);
    }
}
