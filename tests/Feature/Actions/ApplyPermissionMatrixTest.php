<?php

use Slakbal\TenancyFoundation\Actions\Roles\ApplyPermissionMatrix;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrixLoader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    $this->superAdmin = Role::findByName(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $this->support = Role::findByName(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value);
    $this->permission = Permission::findByName(AdminPermissions::TENANT_UPDATE->value, AuthGuard::ADMIN->value);
});

it('applies each changed role and reports a role the sync action refuses without stopping', function () {
    $loaded = app(PermissionMatrixLoader::class)->load(FilamentPanel::ADMIN);

    // A matrix that wrongly treats super_admin as editable, so the sync action has to be the one to refuse it
    $matrix = new PermissionMatrix($loaded->panel, $loaded->roles, $loaded->groups, $loaded->unknown, $loaded->missing, $loaded->held, []);

    $result = app(ApplyPermissionMatrix::class)->handle($matrix, [
        $this->superAdmin->id => [$this->permission->id => true],
        $this->support->id => [$this->permission->id => true],
    ]);

    expect(array_keys($result['failed']))->toBe([AdminRoles::SUPER_ADMIN->value])
        ->and(collect($result['applied'])->map(fn ($change) => $change->role->name)->all())->toBe([AdminRoles::SUPPORT->value])
        ->and($this->support->fresh()->hasPermissionTo(AdminPermissions::TENANT_UPDATE->value))->toBeTrue()
        ->and($this->superAdmin->fresh()->permissions()->count())->toBe(0);
});
