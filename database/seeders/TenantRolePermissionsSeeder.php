<?php

namespace Slakbal\TenancyFoundation\Database\Seeders;

use BackedEnum;
use Illuminate\Database\Seeder;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Spatie\Permission\Models\Permission;
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

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $roleClass = $registrar->getRoleClass();
        $guard = FilamentPanel::TENANT->getAuthGuard();

        $permissions = collect($this->permissionEnums())
            ->flatMap(fn (string $enum): array => $enum::cases())
            ->map(fn (BackedEnum $permission): Permission => Permission::findOrCreate(
                name: $permission->value,
                guardName: $guard,
            ));

        // The owner bypasses every check through Gate::before, so the role carries no permission rows
        $roleClass::findOrCreate(name: TenantRoles::OWNER->value, guardName: $guard)
            ->syncPermissions([]);

        $roleClass::findOrCreate(name: TenantRoles::ADMIN->value, guardName: $guard)
            ->syncPermissions($permissions);

        $viewOnly = collect($this->viewOnlyPermissions())
            ->map(fn (BackedEnum $permission): string => $permission->value)
            ->all();

        $roleClass::findOrCreate(name: TenantRoles::VIEW_ONLY->value, guardName: $guard)
            ->syncPermissions($permissions->whereIn('name', $viewOnly));
    }
}
