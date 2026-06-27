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
                    $service = app(\App\Services\GoogleSearchConsoleService::class);
                    try {
                        $sites = $service->listSites();
                        foreach ($sites as $siteData) {
                            \App\Models\GscSite::updateOrCreate(
                                ['site_url' => $siteData['siteUrl']],
                                [
                                    'name' => parse_url($siteData['siteUrl'], PHP_URL_HOST) ?: $siteData['siteUrl'],
                                    'permission_level' => $siteData['permissionLevel'],
                                    'is_active' => true
                                ]
                            );
                        }
                        \Filament\Notifications\Notification::make()
                            ->title('Sites synced successfully')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Failed to sync sites')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
