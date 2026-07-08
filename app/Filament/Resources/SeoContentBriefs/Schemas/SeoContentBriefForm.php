<?php

namespace App\Filament\Resources\SeoContentBriefs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SeoContentBriefForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Hidden::make('keyword_group_id'),
                
                // Outer Layout Grid
                Grid::make(3)
                    ->columnSpan('full')
                    ->schema([
                        // Left Column: General Details, Outline, and SEO Section (spans 2 columns)
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make('Brief General Details')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Article Title')
                                            ->required(),
                                        TextInput::make('slug')
                                            ->label('URL Slug'),
                                        TextInput::make('h1')
                                            ->label('H1 Heading'),
                                        TextInput::make('primary_keyword')
                                            ->label('Primary Keyword')
                                            ->required(),
                                    ])->columns(2),

                                Section::make('Outline Details')
                                    ->schema([
                                        Textarea::make('outline')
                                            ->label('Outline (one heading per line)')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? implode("\n", json_decode($state, true) ?? []) : (is_array($state) ? implode("\n", $state) : ''))
                                            ->dehydrateStateUsing(fn ($state) => json_encode(array_filter(array_map('trim', explode("\n", $state ?? '')))))
                                            ->rows(12)
                                            ->extraInputAttributes(['style' => 'max-height: 1080px; overflow-y: auto;']),
                                        TagsInput::make('must_answer_questions')
                                            ->label('Must Answer Questions')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->dehydrateStateUsing(fn ($state) => json_encode($state ?? [])),
                                    ]),

                                Section::make('SEO & Internal Links')
                                    ->schema([
                                        Textarea::make('seo_notes')
                                            ->label('SEO Notes')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? (json_decode($state, true)['notes'] ?? '') : (is_array($state) ? ($state['notes'] ?? '') : ''))
                                            ->dehydrateStateUsing(fn ($state) => json_encode(['notes' => $state ?? '']))
                                            ->rows(5),
                                        TagsInput::make('internal_link_suggestions')
                                            ->label('Internal Link Suggestions')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->dehydrateStateUsing(fn ($state) => json_encode($state ?? [])),
                                        TagsInput::make('quality_warnings')
                                            ->label('Quality Warnings')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->dehydrateStateUsing(fn ($state) => json_encode($state ?? [])),
                                    ]),
                            ]),

                        // Right Column: Settings, Classification, and Keywords (spans 1 column)
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Brief Settings')
                                    ->schema([
                                        Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->default('draft')
                                            ->required(),
                                    ]),

                                Section::make('Target Classification')
                                    ->schema([
                                        TextInput::make('search_intent')
                                            ->label('Search Intent'),
                                        TextInput::make('content_type')
                                            ->label('Content Type'),
                                        TextInput::make('recommended_action')
                                            ->label('Recommended Action'),
                                    ]),

                                Section::make('SEO Metadata')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Meta Title'),
                                        Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->rows(3),
                                    ]),

                                Section::make('Secondary & FAQ Keywords')
                                    ->schema([
                                        TagsInput::make('secondary_keywords')
                                            ->label('Secondary Keywords')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->dehydrateStateUsing(fn ($state) => json_encode($state ?? [])),
                                        TagsInput::make('faq_keywords')
                                            ->label('FAQ Keywords')
                                            ->formatStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->dehydrateStateUsing(fn ($state) => json_encode($state ?? [])),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
