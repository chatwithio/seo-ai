<?php

namespace App\Filament\Resources\SeoAuditLogs;

use App\Filament\Resources\SeoAuditLogs\Pages\ManageSeoAuditLogs;
use App\Models\SeoAuditLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class SeoAuditLogResource extends Resource
{
    protected static ?string $model = SeoAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static string|UnitEnum|null $navigationGroup = 'SEO Agent';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'gsc_import' => 'warning',
                        'keyword_grouping' => 'info',
                        'content_generation' => 'success',
                        'job_failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'gsc_import' => 'heroicon-o-cloud-arrow-down',
                        'keyword_grouping' => 'heroicon-o-cpu-chip',
                        'content_generation' => 'heroicon-o-document-text',
                        'job_failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-rectangle-stack',
                    })
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'started') => 'gray',
                        str_contains($state, 'finished') || str_contains($state, 'success') => 'success',
                        str_contains($state, 'failed') => 'danger',
                        default => 'info',
                    })
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeoAuditLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
