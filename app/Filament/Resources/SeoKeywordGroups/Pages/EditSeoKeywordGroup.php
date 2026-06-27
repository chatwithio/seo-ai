<?php

namespace App\Filament\Resources\SeoKeywordGroups\Pages;

use App\Filament\Resources\SeoKeywordGroups\SeoKeywordGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeoKeywordGroup extends EditRecord
{
    protected static string $resource = SeoKeywordGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
