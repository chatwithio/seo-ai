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
                    ->icon('heroicon-o-folder-open')
                    ->description('Optional. Connect this article to an existing keyword group and content plan, or leave empty for a standalone article.')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('keyword_group_id')
                            ->label('Keyword Group')
                            ->prefixIcon('heroicon-o-tag')
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
                            ->prefixIcon('heroicon-o-clipboard-document-list')
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
                                Section::make('Article Details')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Article Title')
                                            ->prefixIcon('heroicon-o-document-text')
                                            ->placeholder('e.g. 10 Proven SEO Strategies to Boost Traffic')
                                            ->required(),
                                        TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->prefixIcon('heroicon-o-link')
                                            ->placeholder('10-proven-seo-strategies'),
                                    ])->columns(2),

                                Section::make('Article Content')
                                    ->icon('heroicon-o-pencil-square')
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
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->schema([
                                        Select::make('language')
                                            ->prefixIcon('heroicon-o-language')
                                            ->options([
                                                'English' => 'English',
                                                'Spanish' => 'Spanish',
                                                'French' => 'French',
                                                'Italian' => 'Italian',
                                                'German' => 'German',
                                                'Portuguese' => 'Portuguese',
                                            ]),
                                        Select::make('status')
                                            ->prefixIcon('heroicon-o-check-circle')
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
                                    ->icon('heroicon-o-photo')
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
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        TextInput::make('published_url')
                                            ->label('Published URL')
                                            ->prefixIcon('heroicon-o-arrow-top-right-on-square')
                                            ->url(),
                                        DateTimePicker::make('published_at')
                                            ->label('Published At')
                                            ->prefixIcon('heroicon-o-calendar'),
                                    ]),

                                Section::make('SEO Metadata')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Meta Title')
                                            ->prefixIcon('heroicon-o-sparkles'),
                                        Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->rows(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
