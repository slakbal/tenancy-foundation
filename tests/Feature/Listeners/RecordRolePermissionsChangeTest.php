<?php

use Slakbal\TenancyFoundation\Actions\Roles\SyncRolePermissions;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\RolePermissionChange;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_CREATE] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    }

    $this->role = Role::findOrCreate(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value);
    $this->role->syncPermissions([AdminPermissions::ADMIN_USER_VIEW->value]);
});

test('a change is recorded with the role, the added and removed permissions and the actor', function () {
    $actor = Admin::factory()->create();

    app(SyncRolePermissions::class)->handle($this->role, [AdminPermissions::ADMIN_USER_CREATE], $actor);

    $change = RolePermissionChange::query()->sole();

    expect($change->role_id)->toBe($this->role->getKey())
        ->and($change->role_name)->toBe('support')
        ->and($change->guard_name)->toBe('admin')
        ->and($change->added)->toBe(['admin_user.create'])
        ->and($change->removed)->toBe(['admin_user.view'])
        ->and($change->created_at)->not->toBeNull()
        ->and($change->actor)->toBeInstanceOf(Admin::class)
        ->and($change->actor->is($actor))->toBeTrue();
});

test('a change without an actor is recorded with a null actor and a no-op writes no row', function () {
    app(SyncRolePermissions::class)->handle($this->role, [AdminPermissions::ADMIN_USER_VIEW]);

    expect(RolePermissionChange::query()->count())->toBe(0);

    app(SyncRolePermissions::class)->handle($this->role, []);

    $change = RolePermissionChange::query()->sole();

    expect($change->actor_type)->toBeNull()
        ->and($change->actor_id)->toBeNull()
        ->and($change->removed)->toBe(['admin_user.view']);
});
