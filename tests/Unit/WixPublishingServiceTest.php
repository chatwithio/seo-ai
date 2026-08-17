<?php

namespace Tests\Unit;

use App\Services\WixPublishingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WixPublishingServiceTest extends TestCase
{
    public function test_list_members_returns_mapped_members_from_wix_api(): void
    {
        Http::fake([
            'https://www.wixapis.com/members/v1/members/query' => Http::response([
                'members' => [
                    [
                        'id' => 'member-123',
                        'profile' => [
                            'nickname' => 'John SEO',
                            'slug' => 'john-seo',
                        ],
                        'loginEmail' => 'john@example.com',
                    ],
                    [
                        'id' => 'member-456',
                        'profile' => [
                            'nickname' => 'Jane Editor',
                        ],
                        'loginEmail' => 'jane@example.com',
                    ],
                ],
            ], 200),
        ]);

        $service = new WixPublishingService;
        $members = $service->listMembers('fake-api-key', 'fake-site-id', 10);

        $this->assertCount(2, $members);
        $this->assertSame('John SEO (john@example.com)', $members['member-123']);
        $this->assertSame('Jane Editor (jane@example.com)', $members['member-456']);
    }

    public function test_list_members_returns_empty_array_if_keys_missing(): void
    {
        $service = new WixPublishingService;
        $this->assertSame([], $service->listMembers('', ''));
    }
}
