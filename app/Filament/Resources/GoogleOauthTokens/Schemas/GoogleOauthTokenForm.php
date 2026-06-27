<?php

namespace App\Filament\Resources\GoogleOauthTokens\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GoogleOauthTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('provider')
                    ->required()
                    ->default('google'),
                DateTimePicker::make('expires_at'),
                Textarea::make('scope')
                    ->columnSpanFull(),
            ]);
    }
}
