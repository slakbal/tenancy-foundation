<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    public function content(Schema $schema): Schema
    {
        return parent::content($schema)->components([
            ...parent::content($schema)->getComponents(),
            Text::make(__('Once you have verified your email using the link, refresh this page to continue.')),
        ]);
    }
}
