<?php

namespace App\Filament\Resources\SeoContentImprovements\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SeoContentImprovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page performance')
                ->description('Search Console performance from the latest 90-day scan.')
                ->schema([
                    TextInput::make('page_title')->label('Page title')->disabled(),
                    Placeholder::make('page_link')
                        ->label('Current page')
                        ->content(fn ($record): HtmlString => new HtmlString(
                            '<a class="text-primary-600 hover:underline" target="_blank" rel="noopener noreferrer" href="'.e($record?->page_url).'">'.e($record?->page_url).'</a>'
                        )),
                    Grid::make(4)->schema([
                        TextInput::make('clicks')->disabled(),
                        TextInput::make('impressions')->disabled(),
                        TextInput::make('ctr')->label('CTR')->disabled(),
                        TextInput::make('position')->disabled(),
                    ]),
                ]),
            Section::make('AI improvement idea')
                ->description('Generate an idea first, then create a complete editable article rewrite.')
                ->schema([
                    Textarea::make('suggested_paragraph')->rows(8)->disabled(),
                    Textarea::make('rationale')->rows(4)->disabled(),
                    Placeholder::make('target_keyword_list')
                        ->label('Target keywords')
                        ->content(fn ($record): string => implode(', ', $record?->target_keywords ?? []) ?: 'Not generated yet'),
                    Placeholder::make('last_error_display')
                        ->label('Last error')
                        ->content(fn ($record): string => $record?->last_error ?: 'None')
                        ->visible(fn ($record): bool => filled($record?->last_error)),
                ]),
        ]);
    }
}
