<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages;

use Filament\Resources\Pages\ListRecords;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
