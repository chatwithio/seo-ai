<?php

namespace App\Filament\Resources\GoogleOauthTokens\Pages;

use App\Filament\Resources\GoogleOauthTokens\GoogleOauthTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGoogleOauthTokens extends ListRecords
{
    protected static string $resource = GoogleOauthTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('connectGoogle')
                ->label('Connect Google Account')
                ->icon('heroicon-o-link')
                ->color('success')
                ->url('/google/connect'),
        ];
    }
}
