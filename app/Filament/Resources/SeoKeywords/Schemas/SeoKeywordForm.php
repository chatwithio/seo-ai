<?php

namespace App\Filament\Resources\SeoKeywords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SeoKeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_id')
                    ->required()
                    ->numeric(),
                TextInput::make('query_text')
                    ->required(),
                TextInput::make('normalized_query')
                    ->required(),
                TextInput::make('query_hash')
                    ->required(),
                TextInput::make('language'),
                TextInput::make('country'),
                TextInput::make('total_clicks')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_impressions')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('avg_ctr')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('avg_position')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('main_page_url')
                    ->columnSpanFull(),
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
                TextInput::make('priority_score')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('ai_confidence')
                    ->numeric(),
            ]);
    }
}
