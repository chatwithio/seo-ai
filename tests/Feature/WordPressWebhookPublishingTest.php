<?php

namespace Tests\Feature;

use App\Models\ContentPublicationAttempt;
use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;
use App\Models\User;
use App\Services\ContentPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WordPressWebhookPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function draft(string $url = 'https://example.com/?wpwhpro_action=main_test&wpwhpro_api_key=test'): SeoContentDraft
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        PublishingSetting::updateOrCreate(['user_id' => $user->id], [
            'wordpress_webhook_enabled' => true,
            'wordpress_webhook_url' => $url,
            'wordpress_webhook_secret' => 'test-secret',
            'wordpress_post_status' => 'publish',
            'wordpress_post_author' => 'editor@example.com',
            'general_webhook_enabled' => true,
            'general_webhook_url' => 'https://example.com/general',
            'wordpress_email_enabled' => true,
            'wordpress_email' => 'test@example.com',
        ]);

        return SeoContentDraft::create([
            'user_id' => $user->id, 'title' => 'Prueba de publicación',
            'html' => '<p>Contenido de prueba.</p>', 'status' => 'approved', 'content_version' => 1,
        ]);
    }

    public function test_wp_webhooks_creates_post_records_receipt_and_does_not_resend(): void
    {
        $draft = $this->draft();
        Http::fake(['*' => Http::response(['success' => true, 'data' => [
            'post_id' => 1212, 'permalink' => 'https://example.com/article/',
        ]])]);
        $service = app(ContentPublishingService::class);
        $result = $service->publish($draft, 'wordpress_webhook');
        $this->assertSame('1212', $result['external_id']);
        $this->assertSame('https://example.com/article/', $draft->fresh()->published_url);
        $this->assertDatabaseHas('content_publication_attempts', ['external_id' => '1212', 'status' => 'succeeded']);
        Http::assertSent(fn ($r) => $r['action'] === 'create_post'
            && $r['post_type'] === 'post' && $r['post_status'] === 'publish'
            && $r['post_content'] === $draft->html
            && $r->hasHeader('X-SEOAI-Signature', 'sha256='.hash_hmac('sha256', $r->body(), 'test-secret')));
        $this->assertTrue($service->publish($draft, 'wordpress_webhook')['already_delivered']);
        Http::assertSentCount(1);
    }

    public static function invalidResponses(): array
    {
        return [
            'plugin rejection with HTTP 200' => [['success' => false, 'msg' => 'No action defined']],
            'HTML response' => ['<html>Login required</html>'],
            'missing post ID' => [['success' => true]],
            'zero post ID' => [['success' => true, 'data' => ['post_id' => 0]]],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_receipts_are_logged_as_failures(array|string $body): void
    {
        $draft = $this->draft();
        Http::fake(['*' => Http::response($body)]);
        try {
            app(ContentPublishingService::class)->publish($draft, 'wordpress_webhook');
            $this->fail('Invalid receipt accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('WP Webhooks did not confirm post creation', $e->getMessage());
        }
        $this->assertSame('approved', $draft->fresh()->status);
        $this->assertSame('failed', ContentPublicationAttempt::first()->status);
        $this->assertDatabaseHas('seo_audit_logs', ['entity_id' => $draft->id, 'action' => 'content_delivery_failed']);
    }

    public function test_custom_wordpress_receiver_keeps_existing_payload_and_response_contract(): void
    {
        $draft = $this->draft('https://example.com/custom');
        Http::fake(['*' => Http::response(['published_url' => 'https://example.com/custom-post'])]);
        $result = app(ContentPublishingService::class)->publish($draft, 'wordpress_webhook');
        $this->assertSame('https://example.com/custom-post', $result['published_url']);
        Http::assertSent(fn ($r) => ! isset($r['action']) && $r['event'] === 'wordpress.create_post');
    }

    public function test_general_webhook_contract_is_preserved(): void
    {
        $draft = $this->draft();
        Http::fake(['*' => Http::response(['url' => 'https://example.com/general-post'])]);
        $result = app(ContentPublishingService::class)->publish($draft, 'general_webhook');
        $this->assertSame('https://example.com/general-post', $result['published_url']);
        Http::assertSent(fn ($r) => $r['event'] === 'content.ready' && $r['article']['id'] === $draft->id && ! isset($r['action']));
    }

    public function test_wordpress_email_still_delivers(): void
    {
        $draft = $this->draft();
        Mail::shouldReceive('html')->once()->with($draft->html, \Mockery::on(function ($callback) use ($draft) {
            $message = \Mockery::mock(Message::class);
            $message->shouldReceive('to')->once()->with('test@example.com')->andReturnSelf();
            $message->shouldReceive('subject')->once()->with($draft->title)->andReturnSelf();
            $callback($message);

            return true;
        }));
        app(ContentPublishingService::class)->publish($draft, 'wordpress_email');
        $this->assertSame('published', $draft->fresh()->status);
        Http::assertNothingSent();
    }
    public function test_featured_image_is_imported_before_post_with_author_and_clean_html(): void
    {
        $draft = $this->draft();
        PublishingSetting::where('user_id', $draft->user_id)->update(['wordpress_post_author' => 'author=2']);
        $draft->update(['html' => "```html\n<p>Jade</p>\n```", 'featured_image_status' => 'ready',
            'featured_image_url' => 'https://example.com/image.jpg', 'featured_image_alt' => 'Jade image']);
        Http::fakeSequence()->push(['success' => true, 'data' => ['attach_id' => 88]])
            ->push(['success' => true, 'data' => ['post_id' => 99, 'permalink' => 'https://example.com/jade']]);
        app(ContentPublishingService::class)->publish($draft, 'wordpress_webhook');
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['action'] === 'create_url_attachment'
            && $r['url'] === 'https://example.com/image.jpg' && $r['attachment_image_alt'] === 'Jade image');
        Http::assertSent(fn ($r) => $r['action'] === 'create_post' && $r['post_content'] === '<p>Jade</p>'
            && $r['post_author'] === '2' && $r['post_status'] === 'publish'
            && json_decode($r['manage_meta_data'], true)['update_post_meta'][0]['meta_value'] === 88);
    }

    public function test_image_failure_does_not_create_a_post(): void
    {
        $draft = $this->draft();
        $draft->update(['featured_image_status' => 'ready', 'featured_image_url' => 'https://example.com/image.jpg']);
        Http::fake(['*' => Http::response(['success' => false, 'msg' => 'Download failed'])]);
        try {
            app(ContentPublishingService::class)->publish($draft, 'wordpress_webhook');
            $this->fail('Image error accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('WordPress image import failed', $e->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r['action'] === 'create_post');
        $this->assertSame('approved', $draft->fresh()->status);
    }

    public function test_missing_author_fails_before_sending(): void
    {
        $draft = $this->draft();
        PublishingSetting::where('user_id', $draft->user_id)->update(['wordpress_post_author' => null]);
        Http::fake();
        try {
            app(ContentPublishingService::class)->publish($draft, 'wordpress_webhook');
            $this->fail('Missing author accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('WordPress author', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_automatic_delivery_requires_approval_and_enabled_setting(): void
    {
        $draft = $this->draft();
        $service = app(ContentPublishingService::class);
        $this->assertSame([], $service->publishAutomatically($draft)['attempted']);
        PublishingSetting::where('user_id', $draft->user_id)->update([
            'auto_publish_enabled' => true, 'general_webhook_enabled' => false, 'wordpress_email_enabled' => false,
        ]);
        $draft->update(['status' => 'needs_review']);
        $this->assertSame([], $service->publishAutomatically($draft)['attempted']);
        $draft->update(['status' => 'approved']);
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['post_id' => 77]])]);
        $this->assertSame(['wordpress_webhook'], $service->publishAutomatically($draft)['succeeded']);
        $this->assertSame([], $service->publishAutomatically($draft)['attempted']);
        Http::assertSentCount(1);
    }

}
