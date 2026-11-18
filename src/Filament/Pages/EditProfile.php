<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;
use Slakbal\TenancyFoundation\Rules\NotDisposableEmail;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getProfileFormComponents());
    }

    /**
     * @return array<int, Component>
     */
    protected function getProfileFormComponents(): array
    {
        return [
            ...UserNameSchema::components(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        // Only a new address is checked, so a user whose address was listed later can still save the profile.
        return parent::getEmailFormComponent()->rule(new NotDisposableEmail, fn (mixed $state): bool => $state !== $this->getUser()->getAttributeValue('email'));
    }
}
