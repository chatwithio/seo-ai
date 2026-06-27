<?php

namespace App\Filament\Resources\GoogleOauthTokens\Pages;

use App\Filament\Resources\GoogleOauthTokens\GoogleOauthTokenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoogleOauthToken extends CreateRecord
{
    protected static string $resource = GoogleOauthTokenResource::class;
}
