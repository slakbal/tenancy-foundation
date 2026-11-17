<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

const SYNC_COMMAND = 'tenancy-foundation:sync-permissions';

beforeEach(function () {
    $this->seed([AdminRolePermissionsSeeder::class, TenantRolePermissionsSeeder::class]);
});

it('creates a missing permission row for both guards and leaves assignments untouched', function () {
    Permission::findByName(AdminPermissions::USER_DELETE->value, AuthGuard::ADMIN->value)->delete();
    Permission::findByName(TenantPermissions::USER_UPDATE->value, AuthGuard::TENANT->value)->delete();

    $before = Role::findByName(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value)->permissions->pluck('name')->sort()->values()->all();

    $this->artisan(SYNC_COMMAND)->assertSuccessful();

    expect(Permission::where('name', AdminPermissions::USER_DELETE->value)->where('guard_name', AuthGuard::ADMIN->value)->exists())->toBeTrue()
        ->and(Permission::where('name', TenantPermissions::USER_UPDATE->value)->where('guard_name', AuthGuard::TENANT->value)->exists())->toBeTrue()
        ->and(Role::findByName(AdminRoles::ADMIN->value, AuthGuard::ADMIN->value)->permissions->pluck('name'))
        ->not->toContain(AdminPermissions::USER_DELETE->value)
        ->and(Role::findByName(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value)->permissions->pluck('name')->sort()->values()->all())->toBe($before);
});

it('reports an orphaned permission without deleting it', function () {
    Permission::create(['name' => 'legacy.thing', 'guard_name' => AuthGuard::ADMIN->value]);

    $this->artisan(SYNC_COMMAND)
        ->expectsOutputToContain('legacy.thing')
        ->expectsConfirmation('Delete these admin permissions? Every role that holds them loses them.', 'no')
        ->assertSuccessful();

    expect(Permission::where('name', 'legacy.thing')->exists())->toBeTrue();
});

it('keeps orphaned permissions without interaction', function () {
    Permission::create(['name' => 'legacy.thing', 'guard_name' => AuthGuard::ADMIN->value]);

    $this->artisan(SYNC_COMMAND, ['--no-interaction' => true])->assertSuccessful();

    expect(Permission::where('name', 'legacy.thing')->exists())->toBeTrue();
});

it('deletes orphaned permissions and their role assignments once confirmed', function () {
    $orphan = Permission::create(['name' => 'legacy.thing', 'guard_name' => AuthGuard::ADMIN->value]);
    $role = Role::findByName(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value);
    $role->givePermissionTo($orphan);

    $this->artisan(SYNC_COMMAND)
        ->expectsConfirmation('Delete these admin permissions? Every role that holds them loses them.', 'yes')
        ->assertSuccessful();

    expect(Permission::where('name', 'legacy.thing')->exists())->toBeFalse()
        ->and($role->fresh()->permissions->pluck('name'))->not->toContain('legacy.thing');
});

it('is idempotent', function () {
    $this->artisan(SYNC_COMMAND)->assertSuccessful();
    $count = Permission::count();

    $this->artisan(SYNC_COMMAND)->assertSuccessful();

    expect(Permission::count())->toBe($count);
});

it('creates permissions.manage and no seeded role other than super admin holds it', function () {
    $this->artisan(SYNC_COMMAND)->assertSuccessful();

    expect(Permission::where('name', AdminPermissions::PERMISSIONS_MANAGE->value)->where('guard_name', AuthGuard::ADMIN->value)->exists())->toBeTrue();

    Role::where('guard_name', AuthGuard::ADMIN->value)->get()->each(
        fn (Role $role) => expect($role->permissions->pluck('name'))->not->toContain(AdminPermissions::PERMISSIONS_MANAGE->value)
    );
});
