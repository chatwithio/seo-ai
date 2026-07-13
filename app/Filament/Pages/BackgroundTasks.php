<?php

namespace App\Filament\Pages;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Services\BackgroundTaskManager;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BackgroundTasks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Active Jobs';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.background-tasks';

    public function getActiveTasks(): array
    {
        $userId = (int) auth()->id();
        $allTasks = BackgroundTaskManager::listActive();
        $userSiteIds = GscSite::where('user_id', $userId)->pluck('id')->toArray();

        $filtered = [];
        foreach ($allTasks as $lockKey => $task) {
            // New tasks carry explicit ownership and do not need fragile
            // command-string parsing.
            if (array_key_exists('user_id', $task) && $task['user_id'] !== null) {
                if ((int) $task['user_id'] === $userId) {
                    $filtered[$lockKey] = $task;
                }

                continue;
            }

            // Keep compatibility with tasks registered before ownership
            // metadata was added.
            $command = $task['command'] ?? '';

            // Filter by site grouping command
            if (preg_match('/seo:group-keywords\s+(\d+)/', $command, $matches)) {
                $siteId = (int) $matches[1];
                if (! in_array($siteId, $userSiteIds, true)) {
                    continue;
                }
            }

            if (preg_match('/--site-id=(\d+)/', $command, $matches)) {
                $siteId = (int) $matches[1];
                if (! in_array($siteId, $userSiteIds, true)) {
                    continue;
                }
            }

            if (preg_match('/--user-id=(\d+)/', $command, $matches) && (int) $matches[1] !== $userId) {
                continue;
            }

            // Filter by content generation command
            if (preg_match('/--keyword-ids=([0-9,]+)/', $command, $matches)) {
                $kwIds = explode(',', $matches[1]);
                $hasAccess = SeoKeyword::whereIn('id', $kwIds)
                    ->whereIn('site_id', $userSiteIds)
                    ->exists();
                if (! $hasAccess) {
                    continue;
                }
            }

            $filtered[$lockKey] = $task;
        }

        return $filtered;
    }

    public function killTask(string $lockKey)
    {
        if (! array_key_exists($lockKey, $this->getActiveTasks())) {
            Notification::make()
                ->title('Task is no longer running')
                ->warning()
                ->send();

            return;
        }

        BackgroundTaskManager::kill($lockKey);

        Notification::make()
            ->title('Task terminated successfully')
            ->body('The process has been killed and the cache lock released.')
            ->success()
            ->send();
    }
}
