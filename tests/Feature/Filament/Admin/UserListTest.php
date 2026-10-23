<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(1);
});

test('an admin with USER_VIEW_ANY sees users from every tenant in the table', function () {
    $admin = Admin::factory()->create();
    Permission::findOrCreate(AdminPermissions::USER_VIEW_ANY->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo(AdminPermissions::USER_VIEW_ANY);

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $tenantA->members()->attach($userA, ['is_owner' => false, 'is_default_tenant' => true]);
    $tenantB->members()->attach($userB, ['is_owner' => false, 'is_default_tenant' => true]);

    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$userA, $userB])
        ->assertTableColumnExists('tenants.name');
});

test('an admin without USER_VIEW_ANY is forbidden from the users list', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)->assertForbidden();
});

test('searching the users table by name or email finds matching users', function () {
    $admin = Admin::factory()->create();
    Permission::findOrCreate(AdminPermissions::USER_VIEW_ANY->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo(AdminPermissions::USER_VIEW_ANY);

    $match = User::factory()->create(['name' => 'Zephyrine Quill', 'email' => 'zq@example.com']);
    $other = User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);

    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)
        ->searchTable('Zephyr')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('bob@example')
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$match]);
});
