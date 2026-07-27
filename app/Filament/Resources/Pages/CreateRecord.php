<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord as FilamentCreateRecord;

abstract class CreateRecord extends FilamentCreateRecord
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
