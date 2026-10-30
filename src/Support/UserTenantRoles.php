<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserTenantRoles
{
    private ?Collection $roles = null;

    public function __construct(private readonly Model $user) {}

    public function for(Model $tenant): Collection
    {
        return $this->all()->get($tenant->getKey(), collect());
    }

    private function all(): Collection
    {
        return $this->roles ??= DB::table(config('permission.table_names.model_has_roles').' as assigned')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'assigned.'.(config('permission.column_names.role_pivot_key') ?: 'role_id'))
            ->where('assigned.'.(config('permission.column_names.model_morph_key') ?: 'model_id'), $this->user->getKey())
            ->where('assigned.model_type', $this->user->getMorphClass())
            ->whereNotNull('assigned.'.config('permission.column_names.team_foreign_key'))
            ->get(['assigned.'.config('permission.column_names.team_foreign_key').' as tenant_id', 'roles.name'])
            ->groupBy('tenant_id')
            ->map(fn (Collection $rows): Collection => $rows->pluck('name'));
    }
}
