<?php

namespace App\Filament\Resources\GscSites\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GscSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_url')
                    ->url()
                    ->required(),
                TextInput::make('name'),
                TextInput::make('permission_level'),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_imported_at'),
            ]);
    }
}
