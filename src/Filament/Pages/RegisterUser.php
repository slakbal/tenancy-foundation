<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterUser extends Register
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...$this->getNameFormComponents(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected function getNameFormComponents(): array
    {
        return collect(config('tenancy-foundation.user_name_attributes'))
            ->values()
            ->map(fn (string $attribute, int $index): Component => TextInput::make($attribute)
                ->label(__(Str::of($attribute)->replace('_', ' ')->ucfirst()->toString()))
                ->required()
                ->maxLength(100)
                ->autofocus($index === 0))
            ->all();
    }
}
