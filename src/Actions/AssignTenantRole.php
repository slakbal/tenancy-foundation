<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Spatie\Permission\PermissionRegistrar;

readonly class AssignTenantRole
{
    public function handle(Model $user, Model $tenant, TenantRoles $role, ?TenantRoles $replaces = null): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            
            $registrar->getRoleClass()::findOrCreate($role->value, AuthGuard::TENANT->value);

            $registrar->setPermissionsTeamId($tenant->getKey());
            $user->unsetRelation('roles');

            if ($replaces) {
                $user->removeRole($replaces);
            }

            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles');
        }
    }
}
