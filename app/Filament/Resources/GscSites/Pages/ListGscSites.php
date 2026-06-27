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
        ];
    }
}
