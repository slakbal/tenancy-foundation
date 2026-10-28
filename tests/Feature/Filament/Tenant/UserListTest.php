<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Filters\RoleFilter;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    $this->viewer = User::factory()->create();
    $this->tenant->members()->attach($this->viewer, ['is_owner' => false, 'is_default_tenant' => true]);

    setPermissionsTeamId($this->tenant->getKey());
});

test('a tenant user with USER_VIEW_ANY sees only users belonging to the current tenant', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $colleague = User::factory()->create();
    $this->tenant->members()->attach($colleague, ['is_owner' => false, 'is_default_tenant' => true]);

    $outsider = User::factory()->create();
    Tenant::factory()->create()->members()->attach($outsider, ['is_owner' => false, 'is_default_tenant' => true]);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->viewer, $colleague])
        ->assertCanNotSeeTableRecords([$outsider]);
});

test('a tenant user without USER_VIEW_ANY is forbidden from the users list', function () {
    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)->assertForbidden();
});

function globalRoles(TenantRoles ...$roles): void
{
    $team = getPermissionsTeamId();
    setPermissionsTeamId(null);

    foreach ($roles as $role) {
        Role::findOrCreate($role->value, AuthGuard::TENANT->value);
    }

    setPermissionsTeamId($team);
}

test('the users table shows each user\'s role in the current tenant as a badge', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $admin = User::factory()->create();
    $reader = User::factory()->create();
    $none = User::factory()->create();
    foreach ([$admin, $reader, $none] as $user) {
        $this->tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);
    }
    globalRoles(TenantRoles::ADMIN, TenantRoles::VIEW_ONLY);
    $admin->assignRole(TenantRoles::ADMIN);
    $reader->assignRole(TenantRoles::VIEW_ONLY);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableColumnExists('roles.name')
        ->assertTableColumnStateSet('roles.name', [TenantRoles::ADMIN->value], $admin)
        ->assertTableColumnStateSet('roles.name', [TenantRoles::VIEW_ONLY->value], $reader)
        ->assertTableColumnStateSet('roles.name', null, $none)
        ->assertSee('View Only')
        ->assertSee('Admin');
});

test('a role held in another tenant is not shown in this tenant', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);
    $other = Tenant::factory()->create();
    $other->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => false]);

    globalRoles(TenantRoles::ADMIN, TenantRoles::VIEW_ONLY);
    setPermissionsTeamId($other->getKey());
    $member->unsetRelation('roles')->assignRole(TenantRoles::ADMIN);
    setPermissionsTeamId($this->tenant->getKey());
    $member->unsetRelation('roles')->assignRole(TenantRoles::VIEW_ONLY);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('roles.name', [TenantRoles::VIEW_ONLY->value], $member);
});

test('an owner shows the owner role badge', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);
    globalRoles(TenantRoles::OWNER);
    $this->viewer->assignRole(TenantRoles::OWNER);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)->assertTableColumnStateSet('roles.name', [TenantRoles::OWNER->value], $this->viewer)->assertSee('Owner');
});

test('a custom role that is not in the enum still shows as a readable badge', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);
    $team = getPermissionsTeamId();
    setPermissionsTeamId(null);
    Role::findOrCreate('billing_manager', AuthGuard::TENANT->value);
    setPermissionsTeamId($team);
    $this->viewer->assignRole('billing_manager');

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)->assertSee('Billing Manager');
});

function usersWithRoles(object $test): array
{
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $test->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);
    globalRoles(TenantRoles::ADMIN, TenantRoles::VIEW_ONLY);

    $users = ['admin' => User::factory()->create(), 'reader' => User::factory()->create(), 'none' => User::factory()->create()];
    foreach ($users as $user) {
        $test->tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);
    }
    $users['admin']->assignRole(TenantRoles::ADMIN);
    $users['reader']->assignRole(TenantRoles::VIEW_ONLY);

    $test->actingAs($test->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($test->tenant);

    return $users;
}

test('the role filter shows only the users with that role', function () {
    $users = usersWithRoles($this);

    Livewire::test(ListUsers::class)
        ->filterTable('role', [TenantRoles::ADMIN->value])
        ->assertCanSeeTableRecords([$users['admin']])
        ->assertCanNotSeeTableRecords([$users['reader'], $users['none'], $this->viewer]);
});

test('several roles can be picked at once', function () {
    $users = usersWithRoles($this);

    Livewire::test(ListUsers::class)
        ->filterTable('role', [TenantRoles::ADMIN->value, TenantRoles::VIEW_ONLY->value])
        ->assertCanSeeTableRecords([$users['admin'], $users['reader']])
        ->assertCanNotSeeTableRecords([$users['none'], $this->viewer]);
});

test('the no role option finds the users without a role', function () {
    $users = usersWithRoles($this);

    Livewire::test(ListUsers::class)
        ->filterTable('role', [RoleFilter::NONE])
        ->assertCanSeeTableRecords([$users['none'], $this->viewer])
        ->assertCanNotSeeTableRecords([$users['admin'], $users['reader']]);
});

test('no role and a role can be combined', function () {
    $users = usersWithRoles($this);

    Livewire::test(ListUsers::class)
        ->filterTable('role', [RoleFilter::NONE, TenantRoles::ADMIN->value])
        ->assertCanSeeTableRecords([$users['none'], $users['admin'], $this->viewer])
        ->assertCanNotSeeTableRecords([$users['reader']]);
});

test('every user is listed while the role filter is empty', function () {
    $users = usersWithRoles($this);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([...array_values($users), $this->viewer]);
});

test('the role filter only looks at roles in the current tenant', function () {
    usersWithRoles($this);
    $member = User::factory()->create();
    $other = Tenant::factory()->create();
    $other->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => false]);

    setPermissionsTeamId($other->getKey());
    $member->unsetRelation('roles')->assignRole(TenantRoles::ADMIN);
    setPermissionsTeamId($this->tenant->getKey());
    $member->unsetRelation('roles')->assignRole(TenantRoles::VIEW_ONLY);

    Livewire::test(ListUsers::class)
        ->filterTable('role', [TenantRoles::ADMIN->value])
        ->assertCanNotSeeTableRecords([$member])
        ->filterTable('role', [TenantRoles::VIEW_ONLY->value])
        ->assertCanSeeTableRecords([$member]);
});

test('the role filter offers the tenant roles, custom roles and no role but nothing from other guards or tenants', function () {
    usersWithRoles($this);
    $team = getPermissionsTeamId();
    setPermissionsTeamId(null);
    Role::findOrCreate('billing_manager', AuthGuard::TENANT->value);
    Role::findOrCreate('super_admin', AuthGuard::ADMIN->value);
    setPermissionsTeamId($team + 100);
    Role::findOrCreate('other_tenant_only', AuthGuard::TENANT->value);
    setPermissionsTeamId($team);
    Role::findOrCreate('this_tenant_only', AuthGuard::TENANT->value);

    $options = Livewire::test(ListUsers::class)->instance()->getTable()->getFilter('role')->getOptions();

    expect($options)->toHaveKeys([RoleFilter::NONE, 'admin', 'view_only', 'billing_manager', 'this_tenant_only'])
        ->and($options['billing_manager'])->toBe('Billing Manager')
        ->and($options['admin'])->toBe(TenantRoles::ADMIN->getLabel())
        ->and($options)->not->toHaveKeys(['super_admin', 'other_tenant_only']);
});

test('the role filter works next to the trashed filter', function () {
    $users = usersWithRoles($this);
    $users['admin']->delete();

    Livewire::test(ListUsers::class)
        ->filterTable('role', [TenantRoles::ADMIN->value])
        ->assertCanNotSeeTableRecords([$users['admin']])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$users['admin']]);
});
