<?php

namespace App\Filament\Resources\SeoContentDrafts\Tables;

use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;
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
                    ->searchable(),
                TextColumn::make('language')
                    ->badge()
                    ->placeholder('Not recorded'),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('meta_title')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('meta_description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
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
                Action::make('publishContent')
                    ->label('Publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->button()
                    ->modalHeading('Publish Article')
                    ->modalDescription('Choose where this generated article should be delivered.')
                    ->modalSubmitActionLabel('Send Content')
                    ->form([
                        Select::make('channel')
                            ->label('Publishing method')
                            ->options(function (): array {
                                $settings = PublishingSetting::where('user_id', auth()->id())->first();

                                return $settings
                                    ? ContentPublishingService::availableChannels($settings)
                                    : [];
                            })
                            ->placeholder('Configure a method in Settings first')
                            ->helperText('General webhook, WordPress webhook, and WordPress post-by-email are configured under Settings.')
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
