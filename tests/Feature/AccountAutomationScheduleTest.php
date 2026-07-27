<?php

namespace Tests\Feature;

use App\Models\PublishingSetting;
use App\Services\AccountAutomationScheduleService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AccountAutomationScheduleTest extends TestCase
{
    public function test_an_account_is_due_at_its_saved_local_minute(): void
    {
        config(['app.timezone' => 'Europe/Madrid']);
        $settings = new PublishingSetting([
            'automation_publish_time' => '11:23',
        ]);
        $now = CarbonImmutable::parse('2026-07-27 09:23:45', 'UTC');

        $this->assertTrue(
            app(AccountAutomationScheduleService::class)->isScheduledNow($settings, $now),
        );
    }

    public function test_an_account_is_not_due_during_another_minute(): void
    {
        config(['app.timezone' => 'UTC']);
        $settings = new PublishingSetting([
            'automation_publish_time' => '10:43',
        ]);
        $now = CarbonImmutable::parse('2026-07-27 10:44:00', 'UTC');

        $this->assertFalse(
            app(AccountAutomationScheduleService::class)->isScheduledNow($settings, $now),
        );
    }

    public function test_schedule_service_uses_the_application_timezone(): void
    {
        config(['app.timezone' => 'Europe/Madrid']);

        $this->assertSame(
            'Europe/Madrid',
            app(AccountAutomationScheduleService::class)->defaultTimezone(),
        );
    }
}
