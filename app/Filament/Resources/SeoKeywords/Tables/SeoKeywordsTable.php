<?php

namespace App\Filament\Resources\SeoKeywords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class SeoKeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    ->relationship('site', 'site_url')
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
                    ->query(fn($query) => $query->where('total_clicks', '>', 0))
                    ->label('Has Clicks'),
                Filter::make('has_impressions')
                    ->query(fn($query) => $query->where('total_impressions', '>', 0))
                    ->label('Has Impressions'),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('importAllKeywords')
                    ->label('Import Keywords')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {
                        try {
                            set_time_limit(1800);
                            \Illuminate\Support\Facades\Artisan::call('seo:import-all-gsc');
                            \Filament\Notifications\Notification::make()
                                ->title('Keyword import completed')
                                ->body('Successfully imported and aggregated GSC keywords for the past 1 year across all active sites.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\Action::make('groupKeywords')
                    ->label('Auto Group Keywords')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('site_id')
                            ->label('Select Site')
                            ->options(\App\Models\GscSite::pluck('site_url', 'id'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('limit')
                            ->label('Keywords Limit')
                            ->numeric()
                            ->default(50)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        try {
                            set_time_limit(300);
                            \Illuminate\Support\Facades\Artisan::call('seo:group-keywords', [
                                'site_id' => $data['site_id'],
                                '--limit' => $data['limit'],
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Keyword grouping completed')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Grouping failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('generateContent')
                        ->label('Generate Content')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('success')
                        ->form([
                            \Filament\Forms\Components\Select::make('language')
                                ->label('Language')
                                ->options([
                                    'English' => 'English',
                                    'Spanish' => 'Spanish',
                                    'French' => 'French',
                                    'Italian' => 'Italian',
                                    'German' => 'German',
                                    'Portuguese' => 'Portuguese',
                                ])
                                ->default('English')
                                ->required(),
                            \Filament\Forms\Components\Select::make('density')
                                ->label('Keyword Repeat Density (%)')
                                ->options([
                                    '1' => '1%',
                                    '1.5' => '1.5%',
                                    '2' => '2%',
                                    '2.5' => '2.5%',
                                    '3' => '3%',
                                    '3.5' => '3.5%',
                                    '4' => '4%',
                                    '4.5' => '4.5%',
                                    '5' => '5%',
                                    '5.5' => '5.5%',
                                    '6' => '6%',
                                    '6.5' => '6.5%',
                                    '7' => '7%',

                                ])
                                ->default('1.5')
                                ->required(),
                            \Filament\Forms\Components\Select::make('length')
                                ->label('Article Length')
                                ->options([
                                    '500' => 'Short (~500 words)',
                                    '1000' => 'Medium (~1000 words)',
                                    '1500' => 'Long (~1500 words)',
                                ])
                                ->default('1000')
                                ->required(),
                            \Filament\Forms\Components\Textarea::make('hint')
                                ->label('Additional Hint / Context')
                                ->placeholder('e.g. Focus on product value, write in a casual tone')
                                ->rows(3),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            if ($records->isEmpty()) {
                                return;
                            }

                            try {
                                set_time_limit(600);

                                $firstKeyword = $records->first();
                                $site = $firstKeyword->site;

                                if (!$site) {
                                    throw new \Exception("The selected keywords are not associated with a valid site.");
                                }

                                $primaryModel = $records->sortByDesc('total_clicks')->first();

                                $group = \App\Models\SeoKeywordGroup::create([
                                    'site_id' => $site->id,
                                    'group_name' => "AI Generated: " . $primaryModel->query_text,
                                    'slug' => \Illuminate\Support\Str::slug("ai-generated-" . $primaryModel->query_text),
                                    'primary_keyword_id' => $primaryModel->id,
                                    'group_intent' => $primaryModel->intent ?: 'unknown',
                                    'content_type' => 'blog_article',
                                    'recommended_action' => 'create_new_page',
                                    'status' => 'new',
                                ]);

                                foreach ($records as $kw) {
                                    \App\Models\SeoKeywordGroupKeyword::create([
                                        'group_id' => $group->id,
                                        'keyword_id' => $kw->id,
                                        'role' => $kw->id === $primaryModel->id ? 'primary' : 'secondary',
                                    ]);
                                }

                                $generationService = app(\App\Services\SeoContentGenerationService::class);

                                $brief = $generationService->generateBrief($group);

                                $draft = $generationService->generateDraft($brief, [
                                    'density' => $data['density'],
                                    'length' => $data['length'],
                                    'hint' => $data['hint'] ?? '',
                                    'language' => $data['language'],
                                ]);

                                $generationService->reviewDraft($draft);

                                \Filament\Notifications\Notification::make()
                                    ->title('Content generated successfully')
                                    ->body('Draft generated for: ' . $group->group_name)
                                    ->success()
                                    ->actions([
                                        \Filament\Actions\Action::make('viewDraft')
                                            ->label('View Draft')
                                            ->url(\App\Filament\Resources\SeoContentDrafts\Pages\EditSeoContentDraft::getUrl(['record' => $draft->id])),
                                    ])
                                    ->send();

                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
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
