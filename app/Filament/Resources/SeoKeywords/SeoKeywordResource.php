<?php

namespace App\Filament\Resources\SeoKeywords;

use App\Filament\Resources\SeoKeywords\Pages\CreateSeoKeyword;
use App\Filament\Resources\SeoKeywords\Pages\EditSeoKeyword;
use App\Filament\Resources\SeoKeywords\Pages\ListSeoKeywords;
use App\Filament\Resources\SeoKeywords\Schemas\SeoKeywordForm;
use App\Filament\Resources\SeoKeywords\Tables\SeoKeywordsTable;
use App\Models\SeoKeyword;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoKeywordResource extends Resource
{
    protected static ?string $model = SeoKeyword::class;

    protected static ?string $navigationLabel = 'Search Keywords';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SeoKeywordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoKeywordsTable::configure($table);
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
            'index' => ListSeoKeywords::route('/'),
            'create' => CreateSeoKeyword::route('/create'),
            'edit' => EditSeoKeyword::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
