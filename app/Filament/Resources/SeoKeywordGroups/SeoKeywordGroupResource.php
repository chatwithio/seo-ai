<?php

namespace App\Filament\Resources\SeoKeywordGroups;

use App\Filament\Resources\SeoKeywordGroups\Pages\CreateSeoKeywordGroup;
use App\Filament\Resources\SeoKeywordGroups\Pages\EditSeoKeywordGroup;
use App\Filament\Resources\SeoKeywordGroups\Pages\ListSeoKeywordGroups;
use App\Filament\Resources\SeoKeywordGroups\Schemas\SeoKeywordGroupForm;
use App\Filament\Resources\SeoKeywordGroups\Tables\SeoKeywordGroupsTable;
use App\Models\SeoKeywordGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SeoKeywordGroupResource extends Resource
{
    protected static ?string $model = SeoKeywordGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SeoKeywordGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoKeywordGroupsTable::configure($table);
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
            'index' => ListSeoKeywordGroups::route('/'),
            'create' => CreateSeoKeywordGroup::route('/create'),
            'edit' => EditSeoKeywordGroup::route('/{record}/edit'),
        ];
    }
}
