<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\TenantUser;

// Base Class e.g. User model should implement: HasTenants, HasDefaultTenant contracts

trait IsTenantUser
{
    /**
     * Authenticatable User Check
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this instanceof self
            && $panel->getAuthGuard() === AuthGuard::TENANT->getAuthGuard()
            && $this->guard_name === AuthGuard::TENANT->value;

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
    }

    /**
     * Tenant Membership Check
     */
    public function canAccessTenant(Model $tenant): bool
    {
        $this->loadTenantsOnce();

        if ($this->tenants->isEmpty()) {
            return false;
        }

        return $this->tenants->contains('id', $tenant->getKey());
    }

    /**
     * Tenants Relationship
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(config('tenancy-foundation.models.tenant'), 'tenant_user', 'user_id', 'tenant_id')
            ->using(TenantUser::class)
            ->withPivot(['id', 'is_owner', 'is_default_tenant'])
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    /**
     * Loads Tenants Relationship Once - reduces queries for a single request
     */
    public function loadTenantsOnce(): static
    {
        if (! $this->relationLoaded('tenants')) {
            $this->loadMissing('tenants');
        }

        return $this;
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        // 1. Ensure the relationship is loaded in memory once
        $this->loadTenantsOnce();

        return $this->tenants->firstWhere('pivot.is_default_tenant', true)
            ?? $this->tenants->first();
    }
}
