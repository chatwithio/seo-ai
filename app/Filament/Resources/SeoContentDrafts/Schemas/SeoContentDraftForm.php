<?php

namespace App\Filament\Resources\SeoContentDrafts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SeoContentDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword_group_id')
                    ->required()
                    ->numeric(),
                TextInput::make('brief_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug'),
                TextInput::make('meta_title'),
                TextInput::make('meta_description'),
                Textarea::make('html')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('plain_text')
                    ->columnSpanFull(),
                TextInput::make('faq'),
                TextInput::make('internal_link_suggestions'),
                TextInput::make('quality_checks'),
                Select::make('status')
                    ->options([
            'draft' => 'Draft',
            'needs_review' => 'Needs review',
            'approved' => 'Approved',
            'published' => 'Published',
            'rejected' => 'Rejected',
        ])
                    ->default('draft')
                    ->required(),
                Textarea::make('published_url')
                    ->columnSpanFull(),
                DateTimePicker::make('published_at'),
            ]);
    }
}
