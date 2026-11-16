<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Enums\TenantResolution;

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
            && ! $this->hasNoAccessibleTenants()
            && $this->canAccessHostTenant();

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
    }

    // Domain mode: each host is one tenant, so only its active members, and users with a pending invitation to it (who
    // sign in there to accept), may sign in there; Filament's login answers "credentials do not match" otherwise.
    // Without a tenant on the host (console, unknown hosts, which are 404 anyway) there is nothing to check.
    protected function canAccessHostTenant(): bool
    {
        if (TenantResolution::current() !== TenantResolution::DOMAIN) {
            return true;
        }

        $tenant = TenantResolution::DOMAIN->tenantForHost(request()->getHost());

        return $tenant === null || (! $tenant->isCancelled() && ($this->canAccessTenant($tenant) || $this->hasPendingInvitationTo($tenant)));
    }

    protected function hasPendingInvitationTo(Model $tenant): bool
    {
        return config('tenancy-foundation.features.invitations')
            && config('tenancy-foundation.models.invitation')::query()->pending()->where('tenant_id', $tenant->getKey())->where('email', $this->email)->exists();
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
