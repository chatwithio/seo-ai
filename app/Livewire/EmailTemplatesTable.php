<?php

namespace App\Livewire;

use App\Models\EmailTemplate;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;

class EmailTemplatesTable extends TableComponent
{
    public function table(Table $table): Table
    {
        return $table
            ->query(EmailTemplate::query())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Email Template')
                    ->description(fn (EmailTemplate $record): string => match ($record->template_key) {
                        'welcome' => 'Sent after account registration',
                        'weekly_activity' => 'Weekly SEO performance summary',
                        'weekly_ideas' => 'Weekly keyword and content opportunities',
                        default => $record->template_key,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->modalHeading(fn (EmailTemplate $record): string => 'Edit '.$record->name)
                    ->modalSubmitActionLabel('Save Template')
                    ->form([
                        Toggle::make('is_active')
                            ->label('Template enabled'),
                        TextInput::make('subject')
                            ->label('Email subject')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('html_body')
                            ->label('Email body')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->paginated(false)
            ->emptyStateHeading('No email templates')
            ->emptyStateDescription('Email templates are created by the system setup.');
    }

    public function render(): View
    {
        return view('livewire.email-templates-table');
    }
}
