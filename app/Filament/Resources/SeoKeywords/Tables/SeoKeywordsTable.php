<?php

namespace App\Filament\Resources\SeoKeywords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoKeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.site_url')
                    ->label('Site')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('query_text')
                    ->searchable(),
                TextColumn::make('normalized_query')
                    ->searchable(),
                TextColumn::make('query_hash')
                    ->searchable(),
                TextColumn::make('language')
                    ->searchable(),
                TextColumn::make('country')
                    ->searchable(),
                TextColumn::make('total_clicks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_impressions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('avg_ctr')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('avg_position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('intent')
                    ->badge(),
                TextColumn::make('keyword_type')
                    ->badge(),
                TextColumn::make('priority_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ai_confidence')
                    ->numeric()
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
