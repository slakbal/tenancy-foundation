<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
