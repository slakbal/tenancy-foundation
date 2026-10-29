<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;

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
}
