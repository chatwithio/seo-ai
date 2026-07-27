<?php

namespace App\Filament\Resources\GoogleOauthTokens\Pages;

use App\Filament\Resources\GoogleOauthTokens\GoogleOauthTokenResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;

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
