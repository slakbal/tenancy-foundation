<?php

namespace Slakbal\TenancyFoundation\Models;

use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Database\Factories\TenantFactory;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Slakbal\TenancyFoundation\Models\Concerns\Tenancy\IsTenant;

#[Fillable(['name', 'subdomain', 'billing_email', 'is_owner', 'is_default_tenant'])]
class Tenant extends Model implements HasCurrentTenantLabel, HasName
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlidKey, IsTenant;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
