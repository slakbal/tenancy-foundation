<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages;

use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
