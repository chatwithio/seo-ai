<?php

namespace App\Filament\Resources\GscSites\Schemas;

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
                    ->url()
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

                Section::make('AI Agent Configuration')
                    ->description('Set up the targeting parameters for this Search Console property.')
                    ->schema([
                        Toggle::make('agent_enabled')
                            ->label('Enable Automated Agent')
                            ->default(false),
                        Select::make('agent_strategy')
                            ->label('Targeting Strategy')
                            ->options([
                                'low_ctr' => 'Low CTR (High Impressions, Low/No Clicks - High Potential)',
                                'high_clicks' => 'High Clicks (Focus on top performers)',
                            ])
                            ->default('low_ctr')
                            ->reactive()
                            ->required(),
                        TextInput::make('min_impressions')
                            ->numeric()
                            ->label('Minimum Impressions Threshold')
                            ->default(100)
                            ->visible(fn ($get) => $get('agent_strategy') === 'low_ctr'),
                        TextInput::make('max_clicks')
                            ->numeric()
                            ->label('Maximum Clicks Limit')
                            ->default(10)
                            ->visible(fn ($get) => $get('agent_strategy') === 'low_ctr'),
                        TextInput::make('grouping_limit')
                            ->numeric()
                            ->label('Batch Size (Keywords to group at once)')
                            ->default(50)
                            ->required(),
                    ]),
            ]);
    }
}
