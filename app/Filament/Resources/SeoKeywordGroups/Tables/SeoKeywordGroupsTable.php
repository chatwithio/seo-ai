<?php

namespace App\Filament\Resources\SeoKeywordGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoKeywordGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('group_name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('primary_keyword_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('group_intent')
                    ->badge(),
                TextColumn::make('content_type')
                    ->badge(),
                TextColumn::make('recommended_action')
                    ->badge(),
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
                TextColumn::make('opportunity_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ai_confidence')
                    ->numeric()
                    ->sortable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
