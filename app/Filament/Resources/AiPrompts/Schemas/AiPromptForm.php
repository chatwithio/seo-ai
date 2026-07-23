<?php

namespace App\Filament\Resources\AiPrompts\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AiPromptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('How AI Instructions work')
                    ->icon('heroicon-o-light-bulb')
                    ->description('Each instruction controls one step of the SEO workflow. The system automatically inserts the current keywords, site data, language, and content options before sending it to the AI.')
                    ->schema([
                        Placeholder::make('instruction_help')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                                .'Edit the plain-language instructions below to change the result. '
                                .'<strong>Do not remove placeholders</strong> such as '
                                .'<code>{language}</code> or <code>{brief}</code>; they are filled automatically.'
                                .'</p>'
                            )),
                    ]),
                Section::make('Instruction')
                    ->description('Describe what the AI should do and the rules it must follow.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Instruction name')
                            ->helperText('A clear name shown in the AI Instructions list.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Use this instruction')
                            ->helperText('Turn this off only when you want to stop this AI step.')
                            ->required(),
                        Textarea::make('description')
                            ->label('What this instruction does')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('system_prompt')
                            ->label('AI role and permanent rules')
                            ->helperText('Define the AI role, writing standards, and rules that apply every time.')
                            ->rows(8)
                            ->columnSpanFull(),
                        Textarea::make('user_prompt')
                            ->label('Task instructions')
                            ->helperText('Tell the AI exactly what result to create. Keep the existing placeholders.')
                            ->rows(14)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
                Section::make('Advanced settings')
                    ->description('Technical identifiers and response formatting. Most users do not need to change these.')
                    ->icon('heroicon-o-code-bracket')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('prompt_key')
                            ->label('System key')
                            ->helperText('Used internally to select this instruction.')
                            ->disabledOn('edit')
                            ->required(),
                        Textarea::make('output_format')
                            ->label('Required response format')
                            ->helperText('Optional JSON structure expected from the AI.')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
