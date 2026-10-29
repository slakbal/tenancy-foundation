<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\Register;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use SensitiveParameter;
use Slakbal\TenancyFoundation\Actions\Tenant\RegisterTenantOwner;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;

class RegisterUser extends Register
{
    protected Width|string|null $maxWidth = Width::ThreeExtraLarge;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make($this->getWizardSteps())
                ->submitAction($this->getWizardSubmitAction()),
        ]);
    }

    /**
     * @return array<int, Step>
     */
    protected function getWizardSteps(): array
    {
        return [
            Step::make(__('Tenant'))
                ->description(__('Name your tenant and choose its subdomain'))
                ->statePath('tenant')
                ->schema($this->getTenantFormComponents()),

            Step::make(__('Account'))
                ->description(__('Create your owner account'))
                ->schema($this->getUserFormComponents()),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function getTenantFormComponents(): array
    {
        return TenantDetailsSchema::components();
    }

    /**
     * @return array<int, Component>
     */
    protected function getUserFormComponents(): array
    {
        return [
            ...$this->getNameFormComponents(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function getNameFormComponents(): array
    {
        return UserNameSchema::components();
    }

    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(Blade::render(<<<'BLADE'
            <x-filament::button type="submit" size="sm" wire:target="register">
                {{ __('Register') }}
            </x-filament::button>
        BLADE));
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return app(RegisterTenantOwner::class)->handle(
            Arr::get($data, 'tenant', []), // all tenant information
            Arr::except($data, 'tenant'), // all user informations
        );
    }
}
