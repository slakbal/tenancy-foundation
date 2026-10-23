<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Slakbal\TenancyFoundation\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Base Class e.g. Tenant model should implement: HasName, HasCurrentTenantLabel contracts
trait IsTenant
{
    public function getFilamentName(): string
    {
        return "{$this->name}";
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Active';
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(config('tenancy-foundation.models.user'))
            ->using(TenantUser::class)
            ->withPivot(['id', 'is_owner', 'is_default_tenant']) // Register pivot attributes
            ->withTimestamps();
    }
}
