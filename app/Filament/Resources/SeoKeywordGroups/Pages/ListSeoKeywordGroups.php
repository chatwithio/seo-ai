<?php

namespace App\Filament\Resources\SeoKeywordGroups\Pages;

use App\Filament\Resources\SeoKeywordGroups\SeoKeywordGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoKeywordGroups extends ListRecords
{
    protected static string $resource = SeoKeywordGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
