<?php

namespace App\Filament\Resources\SeoContentDrafts\Tables;

use App\Jobs\GenerateArticleImageJob;
use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;
use App\Services\ArticleImageService;
use App\Services\ContentPublishingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoContentDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brief.primary_keyword')
                    ->label('Primary Keyword')
                    ->placeholder('Standalone article')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group.group_name')
                    ->label('Keyword Group')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('keyword_group_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brief_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('Article Title')
                    ->weight('bold')
                    ->icon('heroicon-m-document-text')
                    ->iconColor('primary')
                    ->description(fn (SeoContentDraft $record): ?string => $record->slug ? '🔗 /'.$record->slug : null)
                    ->tooltip(fn (SeoContentDraft $record): ?string => $record->meta_description ?: null)
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language')
                    ->badge()
                    ->placeholder('EN')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'published'  => 'success',
                        'approved'   => 'info',
                        'generating' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'published'  => 'heroicon-m-check-badge',
                        'approved'   => 'heroicon-m-sparkles',
                        'generating' => 'heroicon-m-arrow-path',
                        'failed'     => 'heroicon-m-exclamation-circle',
                        default      => 'heroicon-m-pencil-square',
                    }),
                TextColumn::make('published_url')
                    ->label('Live URL')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconColor('success')
                    ->placeholder('Not published')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(35)
                    ->toggleable(),
                TextColumn::make('featured_image_status')
                    ->label('Image')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?: 'not started')->replace('_', ' ')->title())
                    ->color(fn (?string $state): string => match ($state) {
                        'ready' => 'success',
                        'failed' => 'danger',
                        'generating' => 'warning',
                        'skipped' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('generateImage')
                    ->label(fn (SeoContentDraft $record): string => $record->featured_image_path ? 'Regenerate image' : 'Generate image')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->requiresConfirmation(fn (SeoContentDraft $record): bool => filled($record->featured_image_path))
                    ->action(function (SeoContentDraft $record): void {
                        $record->update([
                            'featured_image_status' => 'queued',
                            'featured_image_error' => null,
                        ]);
                        GenerateArticleImageJob::dispatch($record->id, true);
                        Notification::make()
                            ->title('Featured image queued')
                            ->body('Image generation is running in the background and will appear in Active Jobs.')
                            ->success()
                            ->send();
                    }),
                Action::make('skipImage')
                    ->label('Skip image')
                    ->icon('heroicon-o-forward')
                    ->color('gray')
                    ->visible(fn (SeoContentDraft $record): bool => ! in_array($record->featured_image_status, ['ready', 'skipped'], true))
                    ->action(function (SeoContentDraft $record, ArticleImageService $images): void {
                        $images->skip($record);
                        Notification::make()->title('Image skipped')->success()->send();
                    }),
                Action::make('publishContent')
                    ->label('Publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->button()
                    ->modalIcon('heroicon-o-paper-airplane')
                    ->modalHeading('🚀 Deliver & Publish Article')
                    ->modalDescription('Select a configured publishing channel to deliver this generated SEO article.')
                    ->modalSubmitActionLabel('Publish Content Now')
                    ->form([
                        Select::make('channel')
                            ->label('Publishing Channel')
                            ->options(function (?SeoContentDraft $record): array {
                                $settings = PublishingSetting::where('user_id', auth()->id())->first();

                                return $settings
                                    ? ContentPublishingService::availableChannels($settings, $record)
                                    : [];
                            })
                            ->placeholder('Select a configured publishing channel...')
                            ->helperText('Channels with ✨, 📝, 🏗️, ⚡, ✉️, 🌐 icons are configured in Publishing Settings.')
                            ->native(false)
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SeoContentDraft $record, array $data, ContentPublishingService $publisher): void {
                        try {
                            $result = $publisher->publish($record, $data['channel']);

                            Notification::make()
                                ->title('Content delivered')
                                ->body($result['published_url']
                                    ? 'Published at '.$result['published_url']
                                    : $result['message'])
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Publishing failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
