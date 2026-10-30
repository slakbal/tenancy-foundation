<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
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
            && $this->guard_name === AuthGuard::TENANT->value
            && ! $this->hasNoAccessibleTenants();

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
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

    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_owner', true);
    }

    #[Scope]
    public function withSoleOwnerFlag(Builder $query): void
    {
        $users = $this->getTable();

        $query->addSelect([
            'is_sole_owner' => DB::table('tenant_user as owned')
                ->selectRaw('1')
                ->whereColumn('owned.user_id', $users.'.id')
                ->where('owned.is_owner', true)
                ->whereNotExists(fn ($others) => $others
                    ->selectRaw('1')
                    ->from('tenant_user as co')
                    ->join($users.' as co_users', 'co_users.id', '=', 'co.user_id')
                    ->whereColumn('co.tenant_id', 'owned.tenant_id')
                    ->whereColumn('co.user_id', '!=', 'owned.user_id')
                    ->where('co.is_owner', true)
                    ->whereNull('co_users.deleted_at'))
                ->limit(1),
        ]);
    }

    public function isSoleOwnerOfAnyTenant(): bool
    {
        if ($this->hasAttribute('is_sole_owner')) {
            return (bool) $this->attributes['is_sole_owner'];
        }

        return (bool) static::query()
            ->withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->withSoleOwnerFlag()
            ->value('is_sole_owner');
    }

    public function soleOwnedTenants(): Collection
    {
        return $this->ownedTenants()->get()->filter(fn (Model $tenant): bool => ! $tenant->members()
            ->wherePivot('is_owner', true)
            ->where($this->getQualifiedKeyName(), '!=', $this->getKey())
            ->whereNull($this->qualifyColumn('deleted_at'))
            ->exists());
    }

    public function isOwner(?Model $tenant = null): bool
    {
        $tenant ??= Filament::getTenant();

        if ($tenant === null) {
            return false;
        }

        return (bool) $this->loadTenantsOnce()->tenants->firstWhere('id', $tenant->getKey())?->pivot->is_owner;
    }

    public function memberTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_owner', false);
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

    public function memberStatus(?Model $tenant = null): ?MemberStatus
    {
        $tenant ??= Filament::getTenant();

        return $tenant === null ? null : $this->loadTenantsOnce()->tenants->firstWhere('id', $tenant->getKey())?->pivot->status;
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
