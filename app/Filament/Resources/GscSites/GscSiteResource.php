<?php

namespace App\Filament\Resources\GscSites;

use App\Filament\Resources\GscSites\Pages\CreateGscSite;
use App\Filament\Resources\GscSites\Pages\EditGscSite;
use App\Filament\Resources\GscSites\Pages\ListGscSites;
use App\Filament\Resources\GscSites\Schemas\GscSiteForm;
use App\Filament\Resources\GscSites\Tables\GscSitesTable;
use App\Models\GscSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GscSiteResource extends Resource
{
    protected static ?string $model = GscSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GscSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GscSitesTable::configure($table);
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
            'index' => ListGscSites::route('/'),
            'create' => CreateGscSite::route('/create'),
            'edit' => EditGscSite::route('/{record}/edit'),
        ];
    }
}
