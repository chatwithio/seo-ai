<?php

namespace App\Filament\Resources\SeoContentImprovements;

use App\Filament\Resources\SeoContentImprovements\Pages\EditSeoContentImprovement;
use App\Filament\Resources\SeoContentImprovements\Pages\ListSeoContentImprovements;
use App\Filament\Resources\SeoContentImprovements\Schemas\SeoContentImprovementForm;
use App\Filament\Resources\SeoContentImprovements\Tables\SeoContentImprovementsTable;
use App\Models\SeoContentImprovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoContentImprovementResource extends Resource
{
    protected static ?string $model = SeoContentImprovement::class;

    protected static ?string $navigationLabel = 'Improve Content';

    protected static ?string $slug = 'content-improvements';

    protected static ?string $modelLabel = 'content opportunity';

    protected static ?int $navigationSort = 8;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    public static function form(Schema $schema): Schema
    {
        return SeoContentImprovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoContentImprovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoContentImprovements::route('/'),
            'edit' => EditSeoContentImprovement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->where('is_current', true);
    }
}
