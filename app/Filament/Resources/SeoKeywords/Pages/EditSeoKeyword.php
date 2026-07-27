<?php

namespace App\Filament\Resources\SeoKeywords\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use Filament\Actions\DeleteAction;

class EditSeoKeyword extends EditRecord
{
    protected static string $resource = SeoKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
