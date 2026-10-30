<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Filters\RoleFilter;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
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

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $reader = User::factory()->create();
    $none = User::factory()->create();
    foreach ([$owner, $admin, $reader, $none] as $user) {
        $this->tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);
    }
    globalRoles(TenantRoles::OWNER, TenantRoles::ADMIN, TenantRoles::VIEW_ONLY);
    $owner->assignRole(TenantRoles::OWNER);
    $admin->assignRole(TenantRoles::ADMIN);
    $reader->assignRole(TenantRoles::VIEW_ONLY);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableColumnExists('roles.name')
        ->assertTableColumnStateSet('roles.name', [TenantRoles::OWNER->value], $owner)
        ->assertTableColumnStateSet('roles.name', [TenantRoles::ADMIN->value], $admin)
        ->assertTableColumnStateSet('roles.name', [TenantRoles::VIEW_ONLY->value], $reader)
        ->assertTableColumnStateSet('roles.name', null, $none);
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

function actAsViewerOfUsersWithRoles(object $test): array
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

test('the role filter lists the users matching the picked roles', function (array $picked, array $visible, array $hidden) {
    $users = actAsViewerOfUsersWithRoles($this) + ['viewer' => $this->viewer];

    $list = Livewire::test(ListUsers::class);

    if ($picked !== []) {
        $list->filterTable('role', $picked);
    }

    $list->assertCanSeeTableRecords(array_map(fn (string $key): User => $users[$key], $visible));

    if ($hidden !== []) {
        $list->assertCanNotSeeTableRecords(array_map(fn (string $key): User => $users[$key], $hidden));
    }
})->with([
    'one role' => [[TenantRoles::ADMIN->value], ['admin'], ['reader', 'none', 'viewer']],
    'several roles at once' => [[TenantRoles::ADMIN->value, TenantRoles::VIEW_ONLY->value], ['admin', 'reader'], ['none', 'viewer']],
    'the no role option' => [[RoleFilter::NONE], ['none', 'viewer'], ['admin', 'reader']],
    'no role combined with a role' => [[RoleFilter::NONE, TenantRoles::ADMIN->value], ['none', 'admin', 'viewer'], ['reader']],
    'an empty filter' => [[], ['admin', 'reader', 'none', 'viewer'], []],
]);

test('the role filter only looks at roles in the current tenant', function () {
    actAsViewerOfUsersWithRoles($this);
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
    actAsViewerOfUsersWithRoles($this);
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
    $users = actAsViewerOfUsersWithRoles($this);
    $users['admin']->delete();

    Livewire::test(ListUsers::class)
        ->filterTable('role', [TenantRoles::ADMIN->value])
        ->assertCanNotSeeTableRecords([$users['admin']])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$users['admin']]);
});
