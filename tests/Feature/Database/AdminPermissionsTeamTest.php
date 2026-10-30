<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->adminTeam = Admin::PERMISSIONS_TEAM_ID;
    $this->tenant = Tenant::factory()->create();
});

test('the admin team id can never be a tenant id', function () {
    expect($this->adminTeam)->toBe(0)
        ->and(Tenant::factory()->create()->getKey())->toBeGreaterThan($this->adminTeam);
});

test('an admin has its roles under the admin team only and a tenant user has none there', function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    Role::findOrCreate(TenantRoles::OWNER->value, 'web');
    setPermissionsTeamId($this->adminTeam);
    $admin = Admin::factory()->create();
    $admin->assignRole(AdminRoles::SUPER_ADMIN);
    $user = User::factory()->create(['id' => $admin->getKey()]);
    setPermissionsTeamId($this->tenant->getKey());
    $user->assignRole(TenantRoles::OWNER);

    $admin->unsetRelation('roles');
    $user->unsetRelation('roles');

    expect($admin->hasRole(AdminRoles::SUPER_ADMIN))->toBeFalse()
        ->and($user->hasRole(TenantRoles::OWNER))->toBeTrue();

    setPermissionsTeamId($this->adminTeam);
    $admin->unsetRelation('roles');
    $user->unsetRelation('roles');

    expect($admin->hasRole(AdminRoles::SUPER_ADMIN))->toBeTrue()
        ->and($user->hasRole(TenantRoles::OWNER))->toBeFalse();
});
