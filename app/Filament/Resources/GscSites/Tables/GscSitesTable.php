<?php

namespace App\Filament\Resources\GscSites\Tables;

use App\Models\GscSite;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class GscSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns([
                TextColumn::make('site_url')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('googleOauthToken.email')
                    ->label('Google Account')
                    ->searchable()
                    ->sortable(),
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
                Action::make('runAgent')
                    ->label('Run Agent')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (GscSite $record) {
                        try {
                            set_time_limit(600);
                            Artisan::call('seo:run-agent', ['site_id' => $record->id]);
                            Notification::make()
                                ->title('Agent execution completed')
                                ->body('The AI Agent has completed the keyword grouping and content draft generation cycle.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Agent failed')
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
