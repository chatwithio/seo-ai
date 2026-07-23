<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SeoEmailAutomationService;
use Illuminate\Console\Command;

class SendWeeklySeoEmails extends Command
{
    protected $signature = 'seo:send-weekly-emails {--user-id=}';

    protected $description = 'Send weekly SEO activity and content-idea emails';

    public function handle(SeoEmailAutomationService $emailService): int
    {
        $users = User::query()
            ->when($this->option('user-id'), fn ($query) => $query->whereKey((int) $this->option('user-id')))
            ->get();

        foreach ($users as $user) {
            $emailService->sendWeeklyActivity($user);
            $emailService->sendWeeklyIdeas($user);
        }

        $this->info("Weekly SEO emails processed for {$users->count()} users.");

        return self::SUCCESS;
    }
}
