<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tenant ownership queries. Relies on tenants() from HasTenantMemberships and loadTenantsOnce() from HasTenantAccess.
 */
trait OwnsTenants
{
    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_owner', true);
    }

    public function isOwner(?Model $tenant = null): bool
    {
        $tenant ??= Filament::getTenant();

        if ($tenant === null) {
            return false;
        }

        return (bool) $this->loadTenantsOnce()->tenants->firstWhere('id', $tenant->getKey())?->pivot->is_owner;
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
}
