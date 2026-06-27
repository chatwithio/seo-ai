<?php

namespace App\Filament\Resources\GoogleOauthTokens\Pages;

use App\Filament\Resources\GoogleOauthTokens\GoogleOauthTokenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGoogleOauthToken extends EditRecord
{
    protected static string $resource = GoogleOauthTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
