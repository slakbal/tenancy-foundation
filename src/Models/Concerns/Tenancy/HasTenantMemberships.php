<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Models\TenantUser;

/**
 * Tenant membership relationship and status checks. Relies on loadTenantsOnce() and accessibleTenants() from HasTenantAccess.
 */
trait HasTenantMemberships
{
    /**
     * Tenants Relationship
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(config('tenancy-foundation.models.tenant'), 'tenant_user', 'user_id', 'tenant_id')
            ->using(TenantUser::class)
            ->withPivot(['id', 'is_owner', 'is_default_tenant', 'status'])
            ->withTimestamps();
    }

    public function memberTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_owner', false);
    }

    public function memberStatus(?Model $tenant = null): ?MemberStatus
    {
        $tenant ??= Filament::getTenant();

        return $tenant === null ? null : $this->loadTenantsOnce()->tenants->firstWhere('id', $tenant->getKey())?->pivot->status;
    }

    public function hasOnlyCancelledTenants(): bool
    {
        $this->loadTenantsOnce();

        return $this->tenants->isNotEmpty() && $this->tenants->every->isCancelled();
    }

    public function hasNoAccessibleTenants(): bool
    {
        $this->loadTenantsOnce();

        return $this->tenants->isNotEmpty() && $this->accessibleTenants()->isEmpty();
    }
}
