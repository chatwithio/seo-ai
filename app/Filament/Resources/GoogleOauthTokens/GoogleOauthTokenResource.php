<?php

namespace App\Filament\Resources\GoogleOauthTokens;

use App\Filament\Resources\GoogleOauthTokens\Pages\CreateGoogleOauthToken;
use App\Filament\Resources\GoogleOauthTokens\Pages\EditGoogleOauthToken;
use App\Filament\Resources\GoogleOauthTokens\Pages\ListGoogleOauthTokens;
use App\Filament\Resources\GoogleOauthTokens\Schemas\GoogleOauthTokenForm;
use App\Filament\Resources\GoogleOauthTokens\Tables\GoogleOauthTokensTable;
use App\Models\GoogleOauthToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GoogleOauthTokenResource extends Resource
{
    protected static ?string $model = GoogleOauthToken::class;

    protected static ?string $navigationLabel = 'Google Accounts';
    protected static ?string $pluralModelLabel = 'Google Accounts';
    protected static ?string $modelLabel = 'Google Account';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GoogleOauthTokenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoogleOauthTokensTable::configure($table);
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
            'index' => ListGoogleOauthTokens::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
