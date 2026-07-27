<?php

namespace App\Console\Commands;

use App\Models\PublishingSetting;
use App\Models\User;
use App\Services\AccountAutomationScheduleService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AssignRandomPublishingTimes extends Command
{
    protected $signature = 'seo:assign-random-publishing-times
        {--user-id= : Assign a time to one account}
        {--force : Replace times that users have already saved}
        {--dry-run : Preview assignments without saving them}';

    protected $description = 'Assign staggered non-round automation times to user accounts';

    public function handle(AccountAutomationScheduleService $schedules): int
    {
        $timezone = $schedules->defaultTimezone();
        $userId = filled($this->option('user-id')) ? (int) $this->option('user-id') : null;
        $users = User::query()
            ->when($userId, fn (Builder $query): Builder => $query->whereKey($userId))
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->error($userId ? "User {$userId} was not found." : 'No user accounts were found.');

            return self::FAILURE;
        }

        $rows = [];
        $assigned = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $settings = PublishingSetting::where('user_id', $user->id)->first();

            if ($settings?->automation_publish_time && ! $this->option('force')) {
                $rows[] = [$user->id, $user->email, $settings->automation_publish_time, $timezone, 'Kept'];

                if (! $this->option('dry-run') && $settings->automation_timezone !== $timezone) {
                    $settings->update(['automation_timezone' => $timezone]);
                }

                $skipped++;

                continue;
            }

            $time = $schedules->randomTime();
            $rows[] = [$user->id, $user->email, $time, $timezone, $this->option('dry-run') ? 'Preview' : 'Assigned'];

            if (! $this->option('dry-run')) {
                PublishingSetting::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'automation_publish_time' => $time,
                        'automation_timezone' => $timezone,
                    ],
                );
            }

            $assigned++;
        }

        $this->table(['User', 'Email', 'Time', 'Timezone', 'Result'], $rows);
        $verb = $this->option('dry-run') ? 'would be assigned' : 'assigned';
        $this->info("{$assigned} account(s) {$verb}; {$skipped} existing schedule(s) kept.");

        return self::SUCCESS;
    }
}
