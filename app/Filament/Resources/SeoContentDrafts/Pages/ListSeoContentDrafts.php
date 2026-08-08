<?php

namespace App\Filament\Resources\SeoContentDrafts\Pages;

use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoContentDrafts extends ListRecords
{
    protected static string $resource = SeoContentDraftResource::class;

    public function mount(): void
    {
        parent::mount();

        auth()->user()?->forceFill([
            'articles_last_viewed_at' => now(),
        ])->saveQuietly();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
