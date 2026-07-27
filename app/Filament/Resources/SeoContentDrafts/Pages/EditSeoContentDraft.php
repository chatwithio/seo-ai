<?php

namespace App\Filament\Resources\SeoContentDrafts\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;
use Filament\Actions\DeleteAction;

class EditSeoContentDraft extends EditRecord
{
    protected static string $resource = SeoContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): string
    {
        return 'none';
    }
}
