<?php

namespace App\Filament\Resources\SeoContentDrafts\Pages;

use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoContentDrafts extends ListRecords
{
    protected static string $resource = SeoContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
