<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantUserRoles
{
    private ?Collection $roles = null;

    public function __construct(private readonly Model $tenant) {}

    public function for(Model $user): Collection
    {
        return $this->all()->get($user->getKey(), collect());
    }

    public function userIdsWithRole(string $role): array
    {
        return $this->all()->filter(fn (Collection $names): bool => $names->contains($role))->keys()->all();
    }

    private function all(): Collection
    {
        return $this->roles ??= DB::table(config('permission.table_names.model_has_roles').' as assigned')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'assigned.'.(config('permission.column_names.role_pivot_key') ?: 'role_id'))
            ->where('assigned.'.config('permission.column_names.team_foreign_key'), $this->tenant->getKey())
            ->where('assigned.model_type', app(config('tenancy-foundation.models.user'))->getMorphClass())
            ->get(['assigned.'.(config('permission.column_names.model_morph_key') ?: 'model_id').' as user_id', 'roles.name'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): Collection => $rows->pluck('name'));
    }
}
