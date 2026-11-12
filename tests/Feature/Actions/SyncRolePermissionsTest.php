<?php

use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Roles\SyncRolePermissions;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Admin\RolePermissionsChanged;
use Slakbal\TenancyFoundation\Exceptions\InvalidRolePermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function permissionNames(Role $role): array
{
    return $role->permissions()->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    foreach ([AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_CREATE] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    }

    Permission::findOrCreate(TenantPermissions::USER_VIEW->value, AuthGuard::TENANT->value);

    $this->role = Role::findOrCreate(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value);
    $this->role->syncPermissions([AdminPermissions::ADMIN_USER_VIEW->value, AdminPermissions::ADMIN_USER_VIEW_ANY->value]);
});

test('the permissions are replaced and the added and removed names are reported once', function () {
    Event::fake([RolePermissionsChanged::class]);
    $actor = Admin::factory()->create();

    app(SyncRolePermissions::class)->handle($this->role, [
        AdminPermissions::ADMIN_USER_VIEW,
        AdminPermissions::ADMIN_USER_CREATE->value,
        AdminPermissions::ADMIN_USER_CREATE,
    ], $actor);

    expect(permissionNames($this->role))->toBe(['admin_user.create', 'admin_user.view']);

    Event::assertDispatchedTimes(RolePermissionsChanged::class, 1);
    Event::assertDispatched(RolePermissionsChanged::class, fn (RolePermissionsChanged $event): bool => $event->roleId === $this->role->getKey()
        && $event->roleName === 'support'
        && $event->guardName === 'admin'
        && $event->added === ['admin_user.create']
        && $event->removed === ['admin_user.view_any']
        && $event->actor->is($actor));
});

test('syncing the permissions a role already has does nothing and fires no event', function () {
    Event::fake([RolePermissionsChanged::class]);

    app(SyncRolePermissions::class)->handle($this->role, [AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW]);

    expect(permissionNames($this->role))->toBe(['admin_user.view', 'admin_user.view_any']);
    Event::assertNotDispatched(RolePermissionsChanged::class);
});

test('an empty list removes every permission', function () {
    app(SyncRolePermissions::class)->handle($this->role, []);

    expect(permissionNames($this->role))->toBe([]);
});

test('the immutable roles are refused for both guards', function (string $guard, string $name) {
    Event::fake([RolePermissionsChanged::class]);
    $role = Role::findOrCreate($name, $guard);

    expect(fn () => app(SyncRolePermissions::class)->handle($role, [AdminPermissions::ADMIN_USER_VIEW]))
        ->toThrow(InvalidRolePermissions::class, __('The :role role cannot be changed.', ['role' => $name]));

    expect($role->permissions()->count())->toBe(0);
    Event::assertNotDispatched(RolePermissionsChanged::class);
})->with([
    'admin super admin' => [AuthGuard::ADMIN->value, AdminRoles::SUPER_ADMIN->value],
    'tenant owner' => [AuthGuard::TENANT->value, TenantRoles::OWNER->value],
]);

test('the admin and tenant roles that share a name are not mixed up', function () {
    $role = Role::findOrCreate(TenantRoles::ADMIN->value, AuthGuard::TENANT->value);

    app(SyncRolePermissions::class)->handle($role, [TenantPermissions::USER_VIEW]);

    expect(permissionNames($role))->toBe(['user.view']);
});

test('a permission of another guard or an unknown name is rejected and nothing is persisted', function () {
    Event::fake([RolePermissionsChanged::class]);

    expect(fn () => app(SyncRolePermissions::class)->handle($this->role, [
        AdminPermissions::ADMIN_USER_CREATE,
        TenantPermissions::USER_VIEW,
        'does.not_exist',
    ]))->toThrow(InvalidRolePermissions::class, 'user.view, does.not_exist');

    expect(permissionNames($this->role))->toBe(['admin_user.view', 'admin_user.view_any']);
    Event::assertNotDispatched(RolePermissionsChanged::class);
});

test('the cached permissions are cleared so holders gain and lose abilities immediately', function () {
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    $admin = Admin::factory()->create();
    $admin->assignRole($this->role);

    expect($admin->hasPermissionTo(AdminPermissions::ADMIN_USER_VIEW))->toBeTrue()
        ->and($admin->hasPermissionTo(AdminPermissions::ADMIN_USER_CREATE))->toBeFalse();

    app(SyncRolePermissions::class)->handle($this->role, [AdminPermissions::ADMIN_USER_CREATE]);

    $admin = $admin->fresh();

    expect($admin->hasPermissionTo(AdminPermissions::ADMIN_USER_CREATE))->toBeTrue()
        ->and($admin->hasPermissionTo(AdminPermissions::ADMIN_USER_VIEW))->toBeFalse();
});
