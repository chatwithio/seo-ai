<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

abstract class EditRecord extends FilamentEditRecord
{
    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
