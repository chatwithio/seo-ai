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
        return \App\Services\BackgroundTaskManager::listActive();
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
