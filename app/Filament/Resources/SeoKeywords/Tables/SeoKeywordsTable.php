<?php

namespace App\Filament\Resources\SeoKeywords\Tables;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Services\BackgroundTaskManager;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Process\PhpExecutableFinder;

class SeoKeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->defaultSort('total_clicks', 'desc')
            ->columns([
                TextColumn::make('query_text')
                    ->label('Query')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_clicks')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_impressions')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('avg_ctr')
                    ->label('CTR (%)')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('avg_position')
                    ->label('Position')
                    ->numeric(1)
                    ->sortable(),
                TextColumn::make('content_generation_status')
                    ->label('Content')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        SeoKeyword::CONTENT_GENERATING => 'Generating',
                        SeoKeyword::CONTENT_COMPLETED => 'Created',
                        SeoKeyword::CONTENT_FAILED => 'Failed',
                        default => 'Ready',
                    })
                    ->color(fn (int $state): string => match ($state) {
                        SeoKeyword::CONTENT_GENERATING => 'warning',
                        SeoKeyword::CONTENT_COMPLETED => 'success',
                        SeoKeyword::CONTENT_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('site.site_url')
                    ->label('Site')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('normalized_query')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('query_hash')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('language')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('intent')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('keyword_type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('priority_score')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ai_confidence')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->relationship(
                        'site',
                        'site_url',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                    )
                    ->label('Site'),
                SelectFilter::make('intent')
                    ->options([
                        'informational' => 'Informational',
                        'commercial' => 'Commercial',
                        'transactional' => 'Transactional',
                        'navigational' => 'Navigational',
                        'local' => 'Local',
                        'support' => 'Support',
                        'unknown' => 'Unknown',
                    ]),
                Filter::make('has_clicks')
                    ->query(fn ($query) => $query->where('total_clicks', '>', 0))
                    ->label('Has Clicks'),
                Filter::make('has_impressions')
                    ->query(fn ($query) => $query->where('total_impressions', '>', 0))
                    ->label('Has Impressions'),
                Filter::make('top_impressions')
                    ->query(fn (Builder $query): Builder => $query
                        ->topByImpressionsForUser((int) auth()->id()))
                    ->label('Top Impressions'),
                Filter::make('opportunities')
                    ->query(fn (Builder $query): Builder => $query
                        ->contentOpportunitiesForUser((int) auth()->id()))
                    ->label('High Impressions, Low Clicks'),
            ])
            ->filtersRemoveAllAction(fn (Action $action): Action => $action
                ->label('Clear all filters')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->button())
            ->headerActions([
                Action::make('importAllKeywords')
                    ->label(function (): string {
                        $task = BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Import GSC Keywords');

                        return $task
                            ? 'Importing Keywords — '.($task['progress_percent'] ?? 0).'%'
                            : 'Import Keywords';
                    })
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('warning')
                    ->disabled(fn (): bool => BackgroundTaskManager::findActiveForUser((int) auth()->id(), 'Import GSC Keywords') !== null)
                    ->requiresConfirmation()
                    ->action(function () {
                        try {
                            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                            $basePath = base_path();
                            $userId = (int) auth()->id();
                            exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:import-all-gsc --user-id={$userId} > /dev/null 2>&1 &");

                            Notification::make()
                                ->title('Keyword import started in background')
                                ->body('Importing the latest keyword data. Progress appears on this button and in Active Jobs; this table refreshes automatically.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('groupKeywords')
                    ->label('Auto Group Keywords')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->form([
                        Select::make('site_id')
                            ->label('Select Site')
                            ->options(fn () => GscSite::where('user_id', auth()->id())->pluck('site_url', 'id'))
                            ->required(),
                        TextInput::make('limit')
                            ->label('Maximum Keywords Per AI Batch')
                            ->numeric()
                            ->default(50)
                            ->minValue(1)
                            ->maxValue(200)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        try {
                            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                            $basePath = base_path();
                            $siteId = (int) $data['site_id'];
                            $limit = (int) $data['limit'];
                            exec("cd {$basePath} && {$php} artisan seo:group-keywords {$siteId} --limit={$limit} > /dev/null 2>&1 &");

                            Notification::make()
                                ->title('Keyword grouping started in background')
                                ->body('The auto grouping process is running. Groups will appear shortly.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Grouping failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('generateRowContent')
                    ->label('Generate Content')
                    ->icon('heroicon-m-cpu-chip')
                    ->color('success')
                    ->button()
                    ->visible(fn (SeoKeyword $record): bool => (int) $record->user_id === (int) auth()->id())
                    ->action(function (SeoKeyword $record) {
                        try {
                            $site = $record->site;

                            if (! $site) {
                                throw new \Exception('The selected keyword is not associated with a valid site.');
                            }

                            $keywordIds = $record->id;

                            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                            $basePath = base_path();
                            exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:generate-content --keyword-ids={$keywordIds} > /dev/null 2>&1 &");

                            Notification::make()
                                ->title('Content generation started')
                                ->body('Your content is being created. Track it in Active Jobs, then review it under Articles.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Content generation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('generateContent')
                        ->label('Generate Content')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                return;
                            }

                            try {
                                $records->loadMissing('site');

                                if ($records->contains(fn (SeoKeyword $keyword): bool => ! $keyword->site)) {
                                    throw new \Exception('The selected keywords are not associated with a valid site.');
                                }

                                $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                                $basePath = base_path();

                                foreach ($records->groupBy('site_id') as $siteKeywords) {
                                    $keywordIds = $siteKeywords->pluck('id')->implode(',');
                                    exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:generate-content --keyword-ids={$keywordIds} > /dev/null 2>&1 &");
                                }

                                Notification::make()
                                    ->title('Content generation started')
                                    ->body('The generation process has started in the background. You can check the Audit Logs or GSC Sites drafts list shortly.')
                                    ->success()
                                    ->send();

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Content generation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
