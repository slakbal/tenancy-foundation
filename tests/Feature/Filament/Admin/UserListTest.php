<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
});

test('an admin with USER_VIEW_ANY sees users from every tenant in the table', function () {
    $admin = adminWithAccess(AdminPermissions::USER_VIEW_ANY);

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
        ->assertTableColumnExists('owned_tenants')
        ->assertTableColumnExists('member_tenants');
});

test('an admin without USER_VIEW_ANY is forbidden from the users list', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)->assertForbidden();
});

test('searching the users table by name or email finds matching users', function () {
    $admin = adminWithAccess(AdminPermissions::USER_VIEW_ANY);

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

it('has no create page for tenant users', function () {
    expect(array_keys(UserResource::getPages()))->not->toContain('create');
});

test('bulk force delete needs the force delete permission, not just delete', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->actingAs(adminWithAccess(AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_DELETE, AdminPermissions::USER_RESTORE), AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->assertTableBulkActionHidden('forceDelete');

    $this->actingAs(adminWithAccess(AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_FORCE_DELETE), AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->callTableBulkAction('forceDelete', [$user]);

    expect(User::withTrashed()->whereKey($user->getKey())->exists())->toBeFalse();
});
