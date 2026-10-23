<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
