<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

/**
 * Panel and tenant access checks. Relies on the tenants relationship and hasNoAccessibleTenants() from HasTenantMemberships.
 */
trait HasTenantAccess
{
    /**
     * Authenticatable User Check
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this instanceof self
            && $panel->getAuthGuard() === AuthGuard::TENANT->getAuthGuard()
            && $this->guard_name === AuthGuard::TENANT->value
            && $this->canSignIn()
            && ! $this->hasNoAccessibleTenants();

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
    }

    /**
     * Override in the application to keep user types that must never sign in (for example a learner) out of the panel.
     */
    public function canSignIn(): bool
    {
        return true;
    }

    /**
     * Tenant Membership Check
     */
    public function canAccessTenant(Model $tenant): bool
    {
        $this->loadTenantsOnce();

        return $this->tenants->contains(fn (Model $member): bool => $member->getKey() === $tenant->getKey()
            && $member->pivot->status === MemberStatus::ACTIVE);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->accessibleTenants();
    }

    protected function accessibleTenants(): Collection
    {
        return $this->tenants
            ->reject(fn (Model $tenant): bool => $tenant->isCancelled() || $tenant->pivot->status !== MemberStatus::ACTIVE)
            ->values();
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
        $this->loadTenantsOnce();

        $active = $this->getTenants($panel);

        return $active->firstWhere('pivot.is_default_tenant', true) ?? $active->first();
    }
}
