<?php

namespace App\Filament\Resources\AiPrompts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiPromptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('prompt_key')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('system_prompt')
                    ->columnSpanFull(),
                Textarea::make('user_prompt')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('output_format'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
