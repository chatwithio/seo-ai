<?php

namespace App\Filament\Resources\SeoContentImprovements\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoContentImprovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('clicks', 'desc')
            ->columns([
                TextColumn::make('page_title')
                    ->label('Page')
                    ->placeholder(fn ($record): string => parse_url($record->page_url, PHP_URL_PATH) ?: $record->page_url)
                    ->description(fn ($record): string => $record->page_url)
                    ->limit(55)
                    ->searchable(['page_title', 'page_url'])
                    ->wrap(),
                TextColumn::make('site.site_url')
                    ->label('Site')
                    ->limit(32)
                    ->toggleable(),
                TextColumn::make('clicks')->numeric()->sortable(),
                TextColumn::make('impressions')->numeric()->sortable(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state): string => number_format(((float) $state) * 100, 1).'%')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'draft_generated' => 'success',
                        'recommendation_ready' => 'info',
                        'failed' => 'danger',
                        'generating', 'generating_draft' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('scanned_at')
                    ->label('Updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('period_start')
                    ->label('Scan period')
                    ->formatStateUsing(fn ($state, $record): string => $record->period_start?->format('M j, Y').' - '.$record->period_end?->format('M j, Y'))
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()->label('View ideas'),
            ])
            ->emptyStateHeading('No content opportunities yet')
            ->emptyStateDescription('The weekly scan will show your strongest existing pages here. You can also run seo:refresh-content-improvements for an immediate scan.')
            ->emptyStateIcon('heroicon-o-arrow-trending-up');
    }
}
