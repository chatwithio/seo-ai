<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone Number')
            ->type('tel')
            ->required()
            ->maxLength(30)
            ->rules(['min:7', 'max:30', 'regex:/^[+]?[0-9\s\-\(\).]{7,30}$/'])
            ->validationMessages([
                'regex' => 'Please enter a valid phone number.',
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
