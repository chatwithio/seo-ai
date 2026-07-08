<?php

namespace App\Filament\Resources\GscSites\Pages;

use App\Filament\Resources\GscSites\GscSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGscSites extends ListRecords
{
    protected static string $resource = GscSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \Filament\Actions\Action::make('importAllKeywords')
                ->label('Import Keywords')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        set_time_limit(1800);
                        \Illuminate\Support\Facades\Artisan::call('seo:import-all-gsc');
                        \Filament\Notifications\Notification::make()
                            ->title('Keyword import completed')
                            ->body('Successfully imported and aggregated GSC keywords for the past 1 year across all active sites.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('connectGoogle')
                ->label('Connect Google Account')
                ->icon('heroicon-o-link')
                ->color('success')
                ->url('/google/connect'),
            \Filament\Actions\Action::make('syncSites')
                ->label('Sync Sites from Google')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    $tokens = \App\Models\GoogleOauthToken::where('provider', 'google')->get();
                    if ($tokens->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('No Google Accounts Connected')
                            ->body('Please connect at least one Google account first.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $service = app(\App\Services\GoogleSearchConsoleService::class);
                    $syncedCount = 0;
                    $errors = [];

                    foreach ($tokens as $tokenModel) {
                        try {
                            if (!str_contains($tokenModel->scope ?? '', 'https://www.googleapis.com/auth/webmasters.readonly')) {
                                $errors[] = "Account ({$tokenModel->email}) does not have Search Console permission enabled. Please delete and reconnect it, ensuring the permissions checkbox is checked.";
                                continue;
                            }
                            $sites = $service->listSites($tokenModel);
                            foreach ($sites as $siteData) {
                                \App\Models\GscSite::updateOrCreate(
                                    ['site_url' => $siteData['siteUrl']],
                                    [
                                        'google_oauth_token_id' => $tokenModel->id,
                                        'name' => parse_url($siteData['siteUrl'], PHP_URL_HOST) ?: $siteData['siteUrl'],
                                        'permission_level' => $siteData['permissionLevel'],
                                        'is_active' => true
                                    ]
                               );
                                $syncedCount++;
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Account ({$tokenModel->email}): " . $e->getMessage();
                        }
                    }

                    if (!empty($errors)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sites synced with errors')
                            ->body('Successfully synced ' . $syncedCount . ' sites. Errors: ' . implode(' | ', $errors))
                            ->warning()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sites synced successfully')
                            ->body('Synced ' . $syncedCount . ' sites across all connected Google accounts.')
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
