<?php

namespace App\Filament\Resources\GscSites\Pages;

use App\Filament\Resources\GscSites\GscSiteResource;
use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Services\GoogleSearchConsoleService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\Process\PhpExecutableFinder;

class ListGscSites extends ListRecords
{
    protected static string $resource = GscSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importAllKeywords')
                ->label('Import Keywords')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                        $basePath = base_path();
                        $userId = (int) auth()->id();
                        exec("cd {$basePath} && {$php} artisan seo:import-all-gsc --user-id={$userId} > /dev/null 2>&1 &");

                        Notification::make()
                            ->title('Keyword import started in background')
                            ->body('The import process is running. You can continue working; keywords will update shortly.')
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
                ->label('Sync Sites from Google')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    $userId = (int) auth()->id();
                    $tokens = GoogleOauthToken::where('provider', 'google')
                        ->where('user_id', $userId)
                        ->get();
                    if ($tokens->isEmpty()) {
                        Notification::make()
                            ->title('No Google Accounts Connected')
                            ->body('Please connect at least one Google account first.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $service = app(GoogleSearchConsoleService::class);
                    $syncedCount = 0;
                    $errors = [];

                    foreach ($tokens as $tokenModel) {
                        try {
                            if (! str_contains($tokenModel->scope ?? '', 'https://www.googleapis.com/auth/webmasters.readonly')) {
                                $errors[] = "Account ({$tokenModel->email}) does not have Search Console permission enabled. Please delete and reconnect it, ensuring the permissions checkbox is checked.";

                                continue;
                            }
                            $sites = $service->listSites($tokenModel);
                            foreach ($sites as $siteData) {
                                GscSite::updateOrCreate(
                                    [
                                        'user_id' => $userId,
                                        'site_url' => $siteData['siteUrl'],
                                    ],
                                    [
                                        'google_oauth_token_id' => $tokenModel->id,
                                        'name' => parse_url($siteData['siteUrl'], PHP_URL_HOST) ?: $siteData['siteUrl'],
                                        'permission_level' => $siteData['permissionLevel'],
                                        'is_active' => true,
                                    ]
                                );
                                $syncedCount++;
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Account ({$tokenModel->email}): ".$e->getMessage();
                        }
                    }

                    if (! empty($errors)) {
                        Notification::make()
                            ->title('Sites synced with errors')
                            ->body('Successfully synced '.$syncedCount.' sites. Errors: '.implode(' | ', $errors))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sites synced successfully')
                            ->body('Synced '.$syncedCount.' sites from your connected Google accounts.')
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
