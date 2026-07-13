<?php

namespace App\Filament\Resources\SeoKeywords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SeoKeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Keyword Information')
                    ->schema([
                        Select::make('site_id')
                            ->relationship(
                                'site',
                                'site_url',
                                modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                            )
                            ->label('Site')
                            ->required(),
                        TextInput::make('query_text')
                            ->label('Keyword Query')
                            ->required(),
                        TextInput::make('main_page_url')
                            ->label('Main Page URL')
                            ->url(),
                    ])->columns(1),

                Section::make('Performance & Classification')
                    ->schema([
                        TextInput::make('total_clicks')
                            ->label('Clicks')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('total_impressions')
                            ->label('Impressions')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('avg_ctr')
                            ->label('CTR (%)')
                            ->numeric()
                            ->default(0.0)
                            ->required(),
                        TextInput::make('avg_position')
                            ->label('Position')
                            ->numeric()
                            ->default(1.0)
                            ->required(),
                        Select::make('intent')
                            ->options([
                                'informational' => 'Informational',
                                'commercial' => 'Commercial',
                                'transactional' => 'Transactional',
                                'navigational' => 'Navigational',
                                'local' => 'Local',
                                'support' => 'Support',
                                'unknown' => 'Unknown',
                            ])
                            ->default('unknown')
                            ->required(),
                        Select::make('keyword_type')
                            ->options([
                                'primary_candidate' => 'Primary candidate',
                                'secondary_candidate' => 'Secondary candidate',
                                'question' => 'Question',
                                'brand' => 'Brand',
                                'product' => 'Product',
                                'category' => 'Category',
                                'problem' => 'Problem',
                                'comparison' => 'Comparison',
                                'unknown' => 'Unknown',
                            ])
                            ->default('unknown')
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
