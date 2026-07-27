<?php

namespace App\Filament\Resources\GscSites\Pages;

use App\Filament\Resources\GscSites\GscSiteResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;

class EditGscSite extends EditRecord
{
    protected static string $resource = GscSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
