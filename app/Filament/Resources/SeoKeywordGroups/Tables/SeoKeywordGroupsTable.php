<?php

namespace App\Filament\Resources\SeoKeywordGroups\Tables;

use App\Models\SeoKeywordGroup;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;

class SeoKeywordGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('group_name')
                    ->label('Group Name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('primaryKeyword.query_text')
                    ->label('Primary Keyword')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ai_confidence')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
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
                //
            ])
            ->recordActions([
                Action::make('generateContent')
                    ->label('Generate Content')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->button()
                    ->disabled(fn (?SeoKeywordGroup $record): bool => $record
                        ? Cache::has('seo:generate-content:group:'.$record->id)
                        : false)
                    ->modalHeading('Generate Content from this Group')
                    ->modalDescription('The AI will use the primary keyword and all related keywords in this group to create one content plan and article.')
                    ->form([
                        Select::make('language')
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
                        Select::make('density')
                            ->label('Keyword Repeat Density')
                            ->options([
                                '1' => '1%',
                                '1.5' => '1.5%',
                                '2' => '2%',
                                '2.5' => '2.5%',
                                '3' => '3%',
                            ])
                            ->default('1.5')
                            ->required(),
                        Select::make('length')
                            ->label('Article Length')
                            ->options([
                                '500' => 'Short (~500 words)',
                                '1000' => 'Medium (~1000 words)',
                                '1500' => 'Long (~1500 words)',
                            ])
                            ->default('1000')
                            ->required(),
                        Textarea::make('hint')
                            ->label('Additional Context')
                            ->placeholder('Optional tone, audience, product, or page guidance')
                            ->rows(3),
                    ])
                    ->action(function (SeoKeywordGroup $record, array $data): void {
                        if (! $record->keywords()->exists() && ! $record->primaryKeyword()->exists()) {
                            Notification::make()
                                ->title('This group has no keywords')
                                ->body('Add keywords to the group before generating content.')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                            $basePath = base_path();
                            $groupId = (int) $record->id;
                            $language = escapeshellarg($data['language']);
                            $density = escapeshellarg($data['density']);
                            $length = escapeshellarg($data['length']);
                            $hint = escapeshellarg($data['hint'] ?? '');

                            exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:generate-content --group-id={$groupId} --language={$language} --density={$density} --length={$length} --hint={$hint} > /dev/null 2>&1 &");

                            Notification::make()
                                ->title('Content generation started')
                                ->body('The full keyword group is being turned into a content plan and article. Follow its progress in the sidebar or Active Jobs.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Content generation could not start')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit group'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
