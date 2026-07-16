<?php

namespace App\Filament\Resources\GscSites\Pages;

use App\Filament\Resources\GscSites\GscSiteResource;
use App\Models\GoogleOauthToken;
use App\Services\BackgroundTaskManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\Process\PhpExecutableFinder;

class ListGscSites extends ListRecords
{
    protected static string $resource = GscSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAllKeywords')
                ->label(function (): string {
                    $task = BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Import GSC Keywords');

                    return $task
                        ? 'Importing Keywords — '.($task['progress_percent'] ?? 0).'%'
                        : 'Import Keywords';
                })
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->disabled(fn (): bool => BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Import GSC Keywords') !== null)
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                        $basePath = base_path();
                        $userId = (int) auth()->id();
                        exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:import-all-gsc --user-id={$userId} > /dev/null 2>&1 &");

                        Notification::make()
                            ->title('Keyword import started in background')
                            ->body('Importing the latest keyword data. This button and Active Jobs will show progress, and the keyword table refreshes automatically.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('connectGoogle')
                ->label('Connect Google Account')
                ->icon('heroicon-o-link')
                ->color('success')
                ->url('/google/connect'),
            Action::make('syncSites')
                ->label(function (): string {
                    $task = BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Sync Google Sites');

                    if (! $task) {
                        return 'Sync Sites from Google';
                    }

                    return 'Connecting Sites — '.($task['progress_percent'] ?? 0).'%';
                })
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->disabled(fn (): bool => BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Sync Google Sites') !== null)
                ->action(function () {
                    $userId = (int) auth()->id();
                    $hasTokens = GoogleOauthToken::where('provider', 'google')
                        ->where('user_id', $userId)
                        ->exists();

                    if (! $hasTokens) {
                        Notification::make()
                            ->title('No Google Accounts Connected')
                            ->body('Please connect at least one Google account first.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                        $basePath = base_path();
                        exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:sync-gsc-sites --user-id={$userId} > /dev/null 2>&1 &");

                        Notification::make()
                            ->title('Connecting your sites...')
                            ->body('Google Search Console is loading in the background. The progress button updates automatically as sites appear.')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Could not start site sync')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
