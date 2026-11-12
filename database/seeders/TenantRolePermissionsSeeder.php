<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use BackedEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRolePermissionsSeeder extends Seeder
{
    /**
     * @return array<int, class-string<BackedEnum>>
     */
    protected function permissionEnums(): array
    {
        return array_filter([
            TenantPermissions::class,
            config('tenancy-foundation.features.invitations') ? InvitationPermissions::class : null,
        ]);
    }

    /**
     * @return array<int, BackedEnum>
     */
    protected function viewOnlyPermissions(): array
    {
        return [
            TenantPermissions::USER_VIEW_ANY,
            TenantPermissions::USER_VIEW,
        ];
    }

    /**
     * Every permission the tenant guard knows about, including the app hooks.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return collect($this->permissionEnums())
            ->flatMap(fn (string $enum): array => $enum::cases())
            ->map(fn (BackedEnum $permission): string => $permission->value)
            ->all();
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
        $guard = FilamentPanel::TENANT->getAuthGuard();

        $permissions = collect($this->permissionNames())
            ->map(fn (string $name): Permission => Permission::findOrCreate(
                name: $name,
                guardName: $guard,
            ));

        // The owner bypasses every check through Gate::before, so the role carries no permission rows
        $roleClass::findOrCreate(name: TenantRoles::OWNER->value, guardName: $guard)
            ->syncPermissions([]);

        $this->applyDefaults(
            $roleClass::findOrCreate(name: TenantRoles::ADMIN->value, guardName: $guard),
            $permissions,
        );

        $viewOnly = collect($this->viewOnlyPermissions())
            ->map(fn (BackedEnum $permission): string => $permission->value)
            ->all();

        $this->applyDefaults(
            $roleClass::findOrCreate(name: TenantRoles::VIEW_ONLY->value, guardName: $guard),
            $permissions->whereIn('name', $viewOnly),
        );

        $registrar->forgetCachedPermissions();
    }
}
