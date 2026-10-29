<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

readonly class ChangeAdminUserRole
{
    public function handle(Model $admin, string $role, Model $actor): Model
    {
        if (AdminAccountGuard::isLockedFor($actor, $admin)) {
            return $admin;
        }

        if ($role === AdminRoles::SUPER_ADMIN->value && ! AdminAccountGuard::isSuperAdmin($actor)) {
            throw new AuthorizationException(__('Only a super admin can assign the super admin role.'));
        }

        $admin->syncRoles([$role]);

        return $admin;
    }
}
