<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Filament\Auth\Pages\Login;
use Filament\Schemas\Schema;
use Slakbal\TenancyFoundation\Filament\Pages\Concerns\HasDeveloperSignIn;

class DeveloperSignInLogin extends Login
{
    use HasDeveloperSignIn;

    public function content(Schema $schema): Schema
    {
        return $schema->components($this->devSignInComponents());
    }
}
