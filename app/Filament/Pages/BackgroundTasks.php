<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;
use BackedEnum;

class BackgroundTasks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Background Tasks';

    protected static string|UnitEnum|null $navigationGroup = 'SEO Agent';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.background-tasks';

    public function getActiveTasks(): array
    {
        $allTasks = \App\Services\BackgroundTaskManager::listActive();
        $userSiteIds = \App\Models\GscSite::where('user_id', auth()->id())->pluck('id')->toArray();
        
        $filtered = [];
        foreach ($allTasks as $lockKey => $task) {
            $command = $task['command'] ?? '';
            
            // Filter by site grouping command
            if (preg_match('/seo:group-keywords\s+(\d+)/', $command, $matches)) {
                $siteId = (int) $matches[1];
                if (!in_array($siteId, $userSiteIds)) {
                    continue;
                }
            }
            
            // Filter by content generation command
            if (preg_match('/--keyword-ids=([0-9,]+)/', $command, $matches)) {
                $kwIds = explode(',', $matches[1]);
                $hasAccess = \App\Models\SeoKeyword::whereIn('id', $kwIds)
                    ->whereIn('site_id', $userSiteIds)
                    ->exists();
                if (!$hasAccess) {
                    continue;
                }
            }
            
            $filtered[$lockKey] = $task;
        }
        
        return $filtered;
    }

    public function killTask(string $lockKey)
    {
        \App\Services\BackgroundTaskManager::kill($lockKey);

        \Filament\Notifications\Notification::make()
            ->title('Task terminated successfully')
            ->body('The process has been killed and the cache lock released.')
            ->success()
            ->send();
    }
}
