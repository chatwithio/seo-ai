<?php

namespace App\Filament\Resources\SeoContentBriefs\Pages;

use App\Filament\Resources\SeoContentBriefs\SeoContentBriefResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeoContentBrief extends EditRecord
{
    protected static string $resource = SeoContentBriefResource::class;

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
