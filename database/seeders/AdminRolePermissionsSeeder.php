<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $roleClass = $registrar->getRoleClass();

        $guard = FilamentPanel::ADMIN->getAuthGuard();

        $permissions = collect(AdminPermissions::cases())
            ->map(fn (AdminPermissions $permission): Permission => Permission::findOrCreate(
                name: $permission,
                guardName: $guard,
            ));

        $superAdminRole = $roleClass::findOrCreate(
            name: AdminRoles::SUPER_ADMIN,
            guardName: $guard,
        );
        // $superAdminRole->syncPermissions($permissions);

        $adminRole = $roleClass::findOrCreate(
            name: AdminRoles::ADMIN,
            guardName: $guard,
        );
        $adminRole->syncPermissions($permissions);

        $supportRole = $roleClass::findOrCreate(
            name: AdminRoles::SUPPORT,
            guardName: $guard,
        );
        $supportRole->syncPermissions(
            $permissions->filter(
                fn (Permission $permission): bool => in_array(
                    $permission->name,
                    [
                        AdminPermissions::USER_VIEW_ANY->value,
                        AdminPermissions::USER_VIEW->value,
                        AdminPermissions::IMPERSONATE_USER->value,
                    ],
                    true,
                )
            )
        );
    }
}
