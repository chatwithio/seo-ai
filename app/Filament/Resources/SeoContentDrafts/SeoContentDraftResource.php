<?php

namespace App\Filament\Resources\SeoContentDrafts;

use App\Filament\Resources\SeoContentDrafts\Pages\CreateSeoContentDraft;
use App\Filament\Resources\SeoContentDrafts\Pages\EditSeoContentDraft;
use App\Filament\Resources\SeoContentDrafts\Pages\ListSeoContentDrafts;
use App\Filament\Resources\SeoContentDrafts\Schemas\SeoContentDraftForm;
use App\Filament\Resources\SeoContentDrafts\Tables\SeoContentDraftsTable;
use App\Models\SeoContentDraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoContentDraftResource extends Resource
{
    protected static ?string $model = SeoContentDraft::class;

    protected static ?string $navigationLabel = 'Articles';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SeoContentDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoContentDraftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoContentDrafts::route('/'),
            'create' => CreateSeoContentDraft::route('/create'),
            'edit' => EditSeoContentDraft::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
