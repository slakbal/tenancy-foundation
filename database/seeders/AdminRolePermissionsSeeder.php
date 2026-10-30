<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
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
            $permissions->whereIn('name', [
                AdminPermissions::ADMIN_USER_VIEW_ANY->value,
                AdminPermissions::ADMIN_USER_VIEW->value,
                AdminPermissions::TENANT_VIEW_ANY->value,
                AdminPermissions::TENANT_VIEW->value,
                AdminPermissions::USER_VIEW_ANY->value,
                AdminPermissions::USER_VIEW->value,
                AdminPermissions::USER_SEND_PASSWORD_RESET->value,
                AdminPermissions::IMPERSONATE_USER->value,
                AdminPermissions::CONTACT_REQUEST_VIEW_ANY->value,
                AdminPermissions::CONTACT_REQUEST_VIEW->value,
                AdminPermissions::CONTACT_REQUEST_UPDATE->value,
                AdminPermissions::CONTACT_REQUEST_RESPOND->value,
            ])
        );
    }
}
