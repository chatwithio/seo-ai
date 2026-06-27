<?php

namespace App\Filament\Resources\SeoContentBriefs\Pages;

use App\Filament\Resources\SeoContentBriefs\SeoContentBriefResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoContentBriefs extends ListRecords
{
    protected static string $resource = SeoContentBriefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
