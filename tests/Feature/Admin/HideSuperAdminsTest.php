<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;

beforeEach(function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole(AdminRoles::SUPER_ADMIN);
    $this->admin = Admin::factory()->create();
    $this->admin->assignRole(AdminRoles::ADMIN);
});

test('admins see the super admin by default', function () {
    $this->actingAs($this->admin, AuthGuard::ADMIN->value);

    expect(Admin::query()->count())->toBe(2);
});

test('with hide_super_admins on a non super admin does not see super admins', function () {
    config(['tenancy-foundation.admin.hide_super_admins' => true]);
    $this->actingAs($this->admin, AuthGuard::ADMIN->value);

    expect(Admin::query()->pluck('id')->all())->toBe([$this->admin->getKey()])
        ->and(Admin::query()->whereKey($this->superAdmin->getKey())->exists())->toBeFalse();
});

test('with hide_super_admins on a super admin sees everyone', function () {
    config(['tenancy-foundation.admin.hide_super_admins' => true]);
    $this->actingAs($this->superAdmin, AuthGuard::ADMIN->value);

    expect(Admin::query()->count())->toBe(2);
});

test('with hide_super_admins on guests and commands are not filtered so a super admin can still sign in', function () {
    config(['tenancy-foundation.admin.hide_super_admins' => true]);

    expect(Admin::query()->count())->toBe(2)
        ->and(auth(AuthGuard::ADMIN->value)->getProvider()->retrieveById($this->superAdmin->getKey())?->is($this->superAdmin))->toBeTrue();
});
