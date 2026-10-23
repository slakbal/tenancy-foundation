<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class RegisterUser extends Register
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFirstNameFormComponent(),
                $this->getLastNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getFirstNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label(__('First name'))
            ->required()
            ->maxLength(100)
            ->autofocus();
    }

    protected function getLastNameFormComponent(): Component
    {
        return TextInput::make('last_name')
            ->label(__('Last name'))
            ->required()
            ->maxLength(100);
    }
}
