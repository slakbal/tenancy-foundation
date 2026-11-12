<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionsSeeder extends Seeder
{
    /**
     * Every permission the admin guard knows about, straight from the enum registry.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return array_map(fn (AdminPermissions $permission): string => $permission->value, AdminPermissions::cases());
    }

    /**
     * Role defaults only apply to a role that has no permissions yet (first install),
     * so edits made later (for example through a permissions screen) survive a re-seed.
     */
    protected function applyDefaults(Role $role, Collection $permissions): void
    {
        if ($role->permissions()->exists()) {
            return;
        }

        $role->syncPermissions($permissions);
    }

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $roleClass = $registrar->getRoleClass();

        $guard = FilamentPanel::ADMIN->getAuthGuard();

        $permissions = collect($this->permissionNames())
            ->map(fn (string $name): Permission => Permission::findOrCreate(
                name: $name,
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
        // permissions.manage is deliberately not granted: only super_admin (Gate::before) manages the permission matrix
        $this->applyDefaults($adminRole, $permissions->where('name', '!=', AdminPermissions::PERMISSIONS_MANAGE->value));

        $supportRole = $roleClass::findOrCreate(
            name: AdminRoles::SUPPORT,
            guardName: $guard,
        );
        $this->applyDefaults(
            $supportRole,
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
            ]),
        );

        $registrar->forgetCachedPermissions();
    }
}
