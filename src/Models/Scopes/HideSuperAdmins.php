<?php

namespace Slakbal\TenancyFoundation\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

class HideSuperAdmins implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('tenancy-foundation.admin.hide_super_admins')) {
            return;
        }

        $guard = Auth::guard(AuthGuard::ADMIN->getAuthGuard());

        // hasUser() never loads the user, so resolving the signed in admin itself (and signing in) is not filtered.
        if (! $guard->hasUser() || AdminAccountGuard::isSuperAdmin($guard->user())) {
            return;
        }

        $pivot = config('permission.table_names.model_has_roles');
        $roles = config('permission.table_names.roles');

        $builder->whereNotExists(fn ($query) => $query
            ->select(DB::raw(1))
            ->from($pivot)
            ->join($roles, "{$roles}.id", '=', "{$pivot}.".(config('permission.column_names.role_pivot_key') ?? 'role_id'))
            ->whereColumn("{$pivot}.".config('permission.column_names.model_morph_key'), $model->qualifyColumn($model->getKeyName()))
            ->where("{$pivot}.model_type", $model->getMorphClass())
            ->where("{$roles}.name", AdminRoles::SUPER_ADMIN->value)
            ->where("{$roles}.guard_name", AuthGuard::ADMIN->value));
    }
}
