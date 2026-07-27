<?php

namespace App\Services;

use App\Models\PublishingSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AccountAutomationScheduleService
{
    public function randomTime(): string
    {
        $candidates = $this->candidateTimes();
        $usage = PublishingSetting::query()
            ->whereNotNull('automation_publish_time')
            ->selectRaw('automation_publish_time, COUNT(*) as aggregate')
            ->groupBy('automation_publish_time')
            ->pluck('aggregate', 'automation_publish_time');
        $lowestUsage = $candidates
            ->map(fn (string $time): int => (int) ($usage[$time] ?? 0))
            ->min() ?? 0;
        $leastUsed = $candidates
            ->filter(fn (string $time): bool => (int) ($usage[$time] ?? 0) === $lowestUsage)
            ->values();

        return (string) $leastUsed->random();
    }

    public function defaultTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public function isScheduledNow(PublishingSetting $settings, CarbonInterface $now): bool
    {
        if (blank($settings->automation_publish_time)) {
            return false;
        }

        return $now->copy()->timezone($this->defaultTimezone())->format('H:i')
            === substr((string) $settings->automation_publish_time, 0, 5);
    }

    /**
     * @return Collection<int, string>
     */
    private function candidateTimes(): Collection
    {
        $start = (int) config('automation.random_start_hour', 8);
        $end = (int) config('automation.random_end_hour', 20);

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return collect(range($start, $end))
            ->flatMap(fn (int $hour): array => collect(range(1, 59))
                ->reject(fn (int $minute): bool => $minute % 5 === 0)
                ->map(fn (int $minute): string => sprintf('%02d:%02d', $hour, $minute))
                ->all())
            ->values();
    }
}
