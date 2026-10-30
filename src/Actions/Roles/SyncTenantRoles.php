<?php

namespace Slakbal\TenancyFoundation\Actions\Roles;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Spatie\Permission\PermissionRegistrar;

readonly class SyncTenantRoles
{
    /**
     * @param  array<int, TenantRoles>  $roles
     */
    public function handle(Model $user, Model $tenant, array $roles): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId(null);

            foreach ($roles as $role) {
                $registrar->getRoleClass()::findOrCreate($role->value, AuthGuard::TENANT->value);
            }

            $registrar->setPermissionsTeamId($tenant->getKey());
            $user->unsetRelation('roles');

            $user->syncRoles($user->hasRole(TenantRoles::OWNER) ? [TenantRoles::OWNER, ...$roles] : $roles);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles');
        }
    }
}
