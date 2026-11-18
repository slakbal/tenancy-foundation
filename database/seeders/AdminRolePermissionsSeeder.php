<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use BackedEnum;
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
     * @return array<int, class-string<BackedEnum>>
     */
    protected function permissionEnums(): array
    {
        return [AdminPermissions::class];
    }

    /**
     * The permission enums of the admin guard. Apps add theirs by overriding permissionEnums().
     *
     * @return array<int, class-string<BackedEnum>>
     */
    public function permissionEnumClasses(): array
    {
        return array_values($this->permissionEnums());
    }

    /**
     * Every permission the admin guard knows about, including the app hooks.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return collect($this->permissionEnumClasses())
            ->flatMap(fn (string $enum): array => $enum::cases())
            ->map(fn (BackedEnum $permission): string => $permission->value)
            ->all();
    }

    /**
     * The default permissions of the support role. Apps change them by overriding this method.
     *
     * @return array<int, BackedEnum>
     */
    protected function supportPermissions(): array
    {
        return [
            AdminPermissions::ADMIN_USER_VIEW_ANY,
            AdminPermissions::ADMIN_USER_VIEW,
            AdminPermissions::TENANT_VIEW_ANY,
            AdminPermissions::TENANT_VIEW,
            AdminPermissions::USER_VIEW_ANY,
            AdminPermissions::USER_VIEW,
            AdminPermissions::USER_SEND_PASSWORD_RESET,
            AdminPermissions::IMPERSONATE_USER,
            AdminPermissions::CONTACT_REQUEST_VIEW_ANY,
            AdminPermissions::CONTACT_REQUEST_VIEW,
            AdminPermissions::CONTACT_REQUEST_UPDATE,
            AdminPermissions::CONTACT_REQUEST_RESPOND,
        ];
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
        $support = array_map(fn (BackedEnum $permission): string => $permission->value, $this->supportPermissions());
        $this->applyDefaults($supportRole, $permissions->whereIn('name', $support));

        $registrar->forgetCachedPermissions();
    }
}
