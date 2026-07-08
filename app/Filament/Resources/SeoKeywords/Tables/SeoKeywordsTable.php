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
                            $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
                            $basePath = base_path();
                            exec("cd {$basePath} && {$php} artisan seo:import-all-gsc > /dev/null 2>&1 &");

                            \Filament\Notifications\Notification::make()
                                ->title('Keyword import started in background')
                                ->body('The import process is running. You can continue working; keywords will update shortly.')
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
                            $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
                            $basePath = base_path();
                            $siteId = (int) $data['site_id'];
                            $limit = (int) $data['limit'];
                            exec("cd {$basePath} && {$php} artisan seo:group-keywords {$siteId} --limit={$limit} > /dev/null 2>&1 &");

                            \Filament\Notifications\Notification::make()
                                ->title('Keyword grouping started in background')
                                ->body('The auto grouping process is running. Groups will appear shortly.')
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
            ->actions([
                EditAction::make(),
                \Filament\Actions\Action::make('generateRowContent')
                    ->label('Generate Content')
                    ->icon('heroicon-m-cpu-chip')
                    ->color('success')
                    ->button()
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
                    ->action(function (\App\Models\SeoKeyword $record, array $data) {
                        try {
                            $site = $record->site;

                            if (!$site) {
                                throw new \Exception("The selected keyword is not associated with a valid site.");
                            }

                            $keywordIds = $record->id;
                            $language = escapeshellarg($data['language']);
                            $density = escapeshellarg($data['density']);
                            $length = escapeshellarg($data['length']);
                            $hint = escapeshellarg($data['hint'] ?? '');

                            $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
                            $basePath = base_path();
                            exec("cd {$basePath} && {$php} artisan seo:generate-content --keyword-ids={$keywordIds} --language={$language} --density={$density} --length={$length} --hint={$hint} > /dev/null 2>&1 &");

                            \Filament\Notifications\Notification::make()
                                ->title('Content generation started')
                                ->body('The generation process has started in the background. You can check the Audit Logs or GSC Sites drafts list shortly.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Content generation failed')
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
                                $firstKeyword = $records->first();
                                $site = $firstKeyword->site;

                                if (!$site) {
                                    throw new \Exception("The selected keywords are not associated with a valid site.");
                                }

                                $keywordIds = $records->pluck('id')->implode(',');
                                $language = escapeshellarg($data['language']);
                                $density = escapeshellarg($data['density']);
                                $length = escapeshellarg($data['length']);
                                $hint = escapeshellarg($data['hint'] ?? '');

                                $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
                                $basePath = base_path();
                                exec("cd {$basePath} && {$php} artisan seo:generate-content --keyword-ids={$keywordIds} --language={$language} --density={$density} --length={$length} --hint={$hint} > /dev/null 2>&1 &");

                                \Filament\Notifications\Notification::make()
                                    ->title('Content generation started')
                                    ->body('The generation process has started in the background. You can check the Audit Logs or GSC Sites drafts list shortly.')
                                    ->success()
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
