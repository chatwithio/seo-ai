<?php

namespace App\Filament\Resources\SeoKeywordGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SeoKeywordGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_id')
                    ->required()
                    ->numeric(),
                TextInput::make('group_name')
                    ->required(),
                TextInput::make('slug'),
                TextInput::make('primary_keyword_id')
                    ->numeric(),
                Select::make('group_intent')
                    ->options([
            'informational' => 'Informational',
            'commercial' => 'Commercial',
            'transactional' => 'Transactional',
            'navigational' => 'Navigational',
            'local' => 'Local',
            'support' => 'Support',
            'mixed' => 'Mixed',
            'unknown' => 'Unknown',
        ])
                    ->default('unknown')
                    ->required(),
                Select::make('content_type')
                    ->options([
            'blog_article' => 'Blog article',
            'buying_guide' => 'Buying guide',
            'category_page_improvement' => 'Category page improvement',
            'product_page_improvement' => 'Product page improvement',
            'faq_block' => 'Faq block',
            'comparison_page' => 'Comparison page',
            'landing_page' => 'Landing page',
            'support_article' => 'Support article',
            'no_content_needed' => 'No content needed',
        ])
                    ->default('blog_article')
                    ->required(),
                Select::make('recommended_action')
                    ->options([
            'create_new_page' => 'Create new page',
            'improve_existing_page' => 'Improve existing page',
            'rewrite_meta' => 'Rewrite meta',
            'add_faq' => 'Add faq',
            'merge_with_existing_content' => 'Merge with existing content',
            'no_action' => 'No action',
        ])
                    ->default('create_new_page')
                    ->required(),
                Textarea::make('target_page_url')
                    ->columnSpanFull(),
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
                TextInput::make('opportunity_score')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('ai_summary')
                    ->columnSpanFull(),
                TextInput::make('ai_confidence')
                    ->numeric(),
                Select::make('status')
                    ->options([
            'new' => 'New',
            'review_needed' => 'Review needed',
            'approved' => 'Approved',
            'brief_generated' => 'Brief generated',
            'draft_generated' => 'Draft generated',
            'published' => 'Published',
            'rejected' => 'Rejected',
        ])
                    ->default('new')
                    ->required(),
            ]);
    }
}
