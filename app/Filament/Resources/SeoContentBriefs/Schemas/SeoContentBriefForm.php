<?php

namespace App\Filament\Resources\SeoContentBriefs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeoContentBriefForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword_group_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug'),
                TextInput::make('meta_title'),
                TextInput::make('meta_description'),
                TextInput::make('h1'),
                TextInput::make('primary_keyword')
                    ->required(),
                TextInput::make('secondary_keywords'),
                TextInput::make('faq_keywords'),
                TextInput::make('search_intent'),
                TextInput::make('content_type'),
                TextInput::make('recommended_action'),
                TextInput::make('outline'),
                TextInput::make('internal_link_suggestions'),
                TextInput::make('must_answer_questions'),
                TextInput::make('seo_notes'),
                TextInput::make('quality_warnings'),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->default('draft')
                    ->required(),
            ]);
    }
}
