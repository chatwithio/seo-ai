<?php

namespace App\Filament\Resources\SeoKeywordGroups\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SeoKeywordGroups\SeoKeywordGroupResource;
use Filament\Actions\DeleteAction;

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
