<?php

namespace App\Filament\Resources\GscSites\Schemas;

use App\Filament\Pages\ContentSettings;
use App\Models\GscSite;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class GscSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_url')
                    ->label('Search Console Property')
                    ->helperText('Supports URL properties and domain properties such as sc-domain:seoai.tochat.be.')
                    ->required(),
                TextInput::make('name'),
                Select::make('google_oauth_token_id')
                    ->label('Google Account')
                    ->relationship(
                        'googleOauthToken',
                        'email',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                    )
                    ->placeholder('Select Google Account'),
                TextInput::make('permission_level'),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_imported_at'),

                Section::make('Content and AI Settings')
                    ->description('Content generation and AI agent controls are managed per site under Settings.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn (?GscSite $record): bool => $record !== null)
                    ->headerActions([
                        Action::make('editContentSettings')
                            ->label('Edit content and AI settings')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (?GscSite $record): string => ContentSettings::getUrl(
                                $record ? ['tab' => "content-site-{$record->id}"] : [],
                            )),
                    ])
                    ->schema([]),
            ]);
    }
}
