<?php

namespace App\Filament\Resources\SeoContentBriefs\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\SeoContentBriefs\SeoContentBriefResource;

class CreateSeoContentBrief extends CreateRecord
{
    protected static string $resource = SeoContentBriefResource::class;

    public function getMaxContentWidth(): string
    {
        return 'none';
    }
}
