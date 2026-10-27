<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages;

use Filament\Resources\Pages\ListRecords;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;
}
