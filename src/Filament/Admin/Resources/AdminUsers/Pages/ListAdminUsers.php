<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages;

use Filament\Resources\Pages\ListRecords;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\AdminUserResource;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;
}
