<?php

namespace App\Filament\Resources\SeoContentBriefs;

use App\Filament\Resources\SeoContentBriefs\Pages\CreateSeoContentBrief;
use App\Filament\Resources\SeoContentBriefs\Pages\EditSeoContentBrief;
use App\Filament\Resources\SeoContentBriefs\Pages\ListSeoContentBriefs;
use App\Filament\Resources\SeoContentBriefs\Schemas\SeoContentBriefForm;
use App\Filament\Resources\SeoContentBriefs\Tables\SeoContentBriefsTable;
use App\Models\SeoContentBrief;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoContentBriefResource extends Resource
{
    protected static ?string $model = SeoContentBrief::class;

    protected static ?string $navigationLabel = 'Content Plans';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SeoContentBriefForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoContentBriefsTable::configure($table);
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
            'index' => ListSeoContentBriefs::route('/'),
            'create' => CreateSeoContentBrief::route('/create'),
            'edit' => EditSeoContentBrief::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
