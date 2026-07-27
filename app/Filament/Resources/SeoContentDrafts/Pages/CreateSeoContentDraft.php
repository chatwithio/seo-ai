<?php

namespace App\Filament\Resources\SeoContentDrafts\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;

class CreateSeoContentDraft extends CreateRecord
{
    protected static string $resource = SeoContentDraftResource::class;

    public function getMaxContentWidth(): string
    {
        return 'none';
    }
}
