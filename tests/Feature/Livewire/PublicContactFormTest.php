<?php

use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;

class CustomPublicContactForm extends PublicContactForm
{
    protected static function fields(): array
    {
        return [TextInput::make('phone'), ...parent::fields()];
    }
}

it('renders the public contact form with the default fields', function () {
    Livewire::test(PublicContactForm::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('message')
        ->assertFormFieldDoesNotExist('phone');
});

it('lets a subclass change the fields', function () {
    Livewire::test(CustomPublicContactForm::class)
        ->assertFormFieldExists('phone')
        ->assertFormFieldExists('message');
});

it('registers the configured component under the public form alias', function () {
    expect(config('tenancy-foundation.contact_requests.public_form'))->toBe(PublicContactForm::class);
});
