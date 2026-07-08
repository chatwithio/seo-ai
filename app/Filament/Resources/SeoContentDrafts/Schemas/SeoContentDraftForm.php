<?php

namespace App\Filament\Resources\SeoContentDrafts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SeoContentDraftForm
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
                Hidden::make('brief_id'),
                
                // Outer Layout Grid
                Grid::make(3)
                    ->columnSpan('full')
                    ->schema([
                        // Left Column: General Details & Article Content (spans 2 columns)
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make('Article General Details')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Article Title')
                                            ->placeholder('Enter article title...')
                                            ->required(),
                                        TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->placeholder('url-slug-goes-here'),
                                    ])->columns(2),

                                Section::make('Article Content')
                                    ->schema([
                                        Toggle::make('edit_source')
                                            ->label('View HTML Source Code')
                                            ->live()
                                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                                $state 
                                                    ? $set('html_source', $get('html'))
                                                    : $set('html', $get('html_source'))
                                            ),
                                            
                                        RichEditor::make('html')
                                            ->label('')
                                            ->hidden(fn (callable $get) => $get('edit_source') === true)
                                            ->dehydrateStateUsing(fn ($state, callable $get) => 
                                                $get('edit_source') ? $get('html_source') : $state
                                            )
                                            ->required()
                                            ->extraInputAttributes(['style' => 'max-height: 1080px; overflow-y: auto;'])
                                            ->toolbarButtons([
                                                'attachFiles',
                                                'blockquote',
                                                'bold',
                                                'bulletList',
                                                'codeBlock',
                                                'h2',
                                                'h3',
                                                'italic',
                                                'link',
                                                'orderedList',
                                                'redo',
                                                'strike',
                                                'underline',
                                                'undo',
                                            ]),

                                        Textarea::make('html_source')
                                            ->label('Raw HTML Source')
                                            ->hidden(fn (callable $get) => $get('edit_source') !== true)
                                            ->extraInputAttributes(['style' => 'max-height: 1080px; overflow-y: auto;'])
                                            ->rows(20),
                                    ]),
                            ]),

                        // Right Column: Settings & Metadata (spans 1 column)
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Publishing Settings')
                                    ->schema([
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
                                    ]),

                                Section::make('Publication Details')
                                    ->schema([
                                        TextInput::make('published_url')
                                            ->label('Published URL')
                                            ->url(),
                                        DateTimePicker::make('published_at')
                                            ->label('Published At'),
                                    ]),

                                Section::make('SEO Metadata')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Meta Title'),
                                        Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->rows(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
