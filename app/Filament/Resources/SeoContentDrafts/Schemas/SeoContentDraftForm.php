<?php

namespace App\Filament\Resources\SeoContentDrafts\Schemas;

use App\Models\SeoContentBrief;
use App\Models\SeoKeywordGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                Section::make('Content Source')
                    ->description('Optional. Leave both fields empty for a standalone article, or connect it to an existing keyword group and content plan.')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('keyword_group_id')
                            ->label('Keyword Group')
                            ->options(fn (): array => SeoKeywordGroup::query()
                                ->where('user_id', auth()->id())
                                ->orderBy('group_name')
                                ->pluck('group_name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('brief_id', null))
                            ->placeholder('Standalone article'),
                        Select::make('brief_id')
                            ->label('Content Plan')
                            ->options(fn (Get $get): array => SeoContentBrief::query()
                                ->where('user_id', auth()->id())
                                ->when(
                                    filled($get('keyword_group_id')),
                                    fn ($query) => $query->where('keyword_group_id', $get('keyword_group_id')),
                                )
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                $groupId = SeoContentBrief::query()
                                    ->where('user_id', auth()->id())
                                    ->whereKey($state)
                                    ->value('keyword_group_id');

                                $set('keyword_group_id', $groupId);
                            })
                            ->placeholder('No content plan'),
                    ]),

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
                                            ->dehydrated(false)
                                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => $state
                                                    ? $set('html_source', $get('html'))
                                                    : $set('html', $get('html_source'))
                                            ),

                                        RichEditor::make('html')
                                            ->label('')
                                            ->hidden(fn (callable $get) => $get('edit_source') === true)
                                            ->dehydratedWhenHidden()
                                            ->dehydrateStateUsing(fn ($state, callable $get) => $get('edit_source') ? $get('html_source') : $state
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
                                            ->dehydrated(false)
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
                                        Select::make('language')
                                            ->options([
                                                'English' => 'English',
                                                'Spanish' => 'Spanish',
                                                'French' => 'French',
                                                'Italian' => 'Italian',
                                                'German' => 'German',
                                                'Portuguese' => 'Portuguese',
                                            ]),
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

                                Section::make('Featured Image')
                                    ->description('Upload your own image or use Generate Image from the Articles list.')
                                    ->schema([
                                        FileUpload::make('featured_image_path')
                                            ->label('Article image')
                                            ->image()
                                            ->imageEditor()
                                            ->disk(config('seo_agent.images.disk', 'public'))
                                            ->directory(fn (): string => 'seo-articles/'.auth()->id())
                                            ->visibility('public')
                                            ->maxSize(10240),
                                        TextInput::make('featured_image_alt')
                                            ->label('Image alt text')
                                            ->maxLength(250),
                                        TextInput::make('featured_image_status')
                                            ->label('Image status')
                                            ->disabled()
                                            ->dehydrated(false),
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
