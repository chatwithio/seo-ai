<?php

namespace Tests\Unit;

use App\Services\PublicationRetryPolicy;
use PHPUnit\Framework\TestCase;

class PublicationRetryPolicyTest extends TestCase
{
    public function test_published_articles_can_only_resume_for_multiple_channel_delivery(): void
    {
        $policy = new PublicationRetryPolicy;

        $this->assertTrue($policy->canRun('approved', true, false));
        $this->assertTrue($policy->canRun('published', true, true));
        $this->assertFalse($policy->canRun('published', true, false));
        $this->assertFalse($policy->canRun('approved', false, true));
    }

    public function test_multiple_channel_retry_removes_channels_that_already_succeeded(): void
    {
        $policy = new PublicationRetryPolicy;

        $this->assertSame(
            ['wordpress_webhook', 'wix'],
            $policy->pendingChannels(
                ['wordpress_email', 'wordpress_webhook', 'wix'],
                ['wordpress_email'],
                true,
            ),
        );
    }
}
