<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

readonly class UpdateAdminUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $admin, array $data, Model $actor): Model
    {
        $role = Arr::pull($data, 'role');

        if (AdminAccountGuard::isLockedFor($actor, $admin)) {
            $role = null;
        }

        if ($role === AdminRoles::SUPER_ADMIN->value && ! AdminAccountGuard::isSuperAdmin($actor)) {
            throw new AuthorizationException(__('Only a super admin can assign the super admin role.'));
        }

        return DB::transaction(function () use ($admin, $data, $role): Model {
            $attributes = [...config('tenancy-foundation.user_name_attributes'), 'email'];

            $admin->forceFill(Arr::only($data, $attributes))->save();

            if ($role !== null) {
                $admin->syncRoles([$role]);
            }

            return $admin;
        });
    }
}
