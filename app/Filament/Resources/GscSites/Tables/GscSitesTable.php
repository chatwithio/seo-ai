<?php

namespace App\Filament\Resources\GscSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GscSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_url')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('permission_level')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('last_imported_at')
                    ->dateTime()
                    ->sortable(),
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
            ->actions([
                EditAction::make(),
                \Filament\Actions\Action::make('importKeywords')
                    ->label('Import Keywords')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->default(now()->subDays(3)->format('Y-m-d'))
                            ->required()
                    ])
                    ->action(function (\App\Models\GscSite $record, array $data) {
                        try {
                            set_time_limit(300);
                            \Illuminate\Support\Facades\Artisan::call('seo:import-gsc', [
                                'site_id' => $record->id,
                                '--date' => $data['date']
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Import completed successfully')
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
                \Filament\Actions\Action::make('aggregateKeywords')
                    ->label('Aggregate Keywords')
                    ->icon('heroicon-o-circle-stack')
                    ->color('info')
                    ->action(function (\App\Models\GscSite $record) {
                        try {
                            \Illuminate\Support\Facades\Artisan::call('seo:aggregate-keywords', ['site_id' => $record->id]);
                            \Filament\Notifications\Notification::make()
                                ->title('Keywords aggregated successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Aggregation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
