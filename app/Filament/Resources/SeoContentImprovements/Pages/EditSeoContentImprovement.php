<?php

namespace App\Filament\Resources\SeoContentImprovements\Pages;

use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;
use App\Filament\Resources\SeoContentImprovements\SeoContentImprovementResource;
use App\Jobs\GenerateContentImprovementDraftJob;
use App\Jobs\GenerateContentImprovementRecommendationJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSeoContentImprovement extends EditRecord
{
    protected static string $resource = SeoContentImprovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRecommendation')
                ->label($this->record->suggested_paragraph ? 'Refresh idea' : 'Generate idea')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'recommendation_queued', 'last_error' => null]);
                    GenerateContentImprovementRecommendationJob::dispatch($this->record->id, true);
                    Notification::make()
                        ->title('Improvement idea queued')
                        ->body('Generation is running in the background. Progress will appear in Active Jobs.')
                        ->success()
                        ->send();
                }),
            Action::make('generateRewrite')
                ->label($this->record->generated_draft_id ? 'Open rewrite' : 'Generate full rewrite')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->requiresConfirmation(fn (): bool => ! $this->record->generated_draft_id)
                ->action(function (): void {
                    if ($this->record->generated_draft_id) {
                        $this->redirect(SeoContentDraftResource::getUrl('edit', ['record' => $this->record->generated_draft_id]));

                        return;
                    }

                    $this->record->update(['status' => 'draft_queued', 'last_error' => null]);
                    GenerateContentImprovementDraftJob::dispatch($this->record->id);
                    Notification::make()
                        ->title('Full rewrite queued')
                        ->body('The article, image, and review will run in the background. Progress will appear in Active Jobs.')
                        ->success()
                        ->send();
                }),
            Action::make('openCurrentPage')
                ->label('Open current page')
                ->url(fn (): string => $this->record->page_url)
                ->openUrlInNewTab()
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
