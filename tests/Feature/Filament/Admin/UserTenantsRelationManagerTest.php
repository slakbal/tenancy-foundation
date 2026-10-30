<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\RelationManagers\TenantsRelationManager;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;
use Spatie\Permission\Models\Permission;

function tenantsActor(AdminPermissions ...$permissions): Admin
{
    $admin = adminWithAccess(AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_VIEW, AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, ...$permissions);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);

    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->tenant->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => true]);
});

function tenantsManager(User $user)
{
    return Livewire::test(TenantsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class]);
}

it('lists the tenants of the user with owner flag and role', function () {
    tenantsActor();

    tenantsManager($this->owner)
        ->assertCanSeeTableRecords([$this->tenant])
        ->assertSee('Acme')
        ->assertSee('acme')
        ->assertSee(TenantRoles::OWNER->getLabel());
});

it('shows only the tenants of that user', function () {
    tenantsActor();
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());

    tenantsManager($this->member)
        ->assertCanSeeTableRecords([$this->tenant])
        ->assertCanNotSeeTableRecords([$other]);
});

it('reads the roles of a user per tenant', function () {
    $roles = new UserTenantRoles($this->owner);

    expect($roles->for($this->tenant)->all())->toBe([TenantRoles::OWNER->value])
        ->and($roles->for(Tenant::factory()->create())->all())->toBe([]);
});

it('makes a member an owner of the tenant next to the existing owner', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);

    tenantsManager($this->member)
        ->callAction(TestAction::make('makeOwner')->table($this->tenant))
        ->assertNotified();

    expect($this->member->isOwner($this->tenant))->toBeTrue()
        ->and($this->owner->fresh()->isOwner($this->tenant))->toBeTrue();
});

it('offers neither make owner nor a role change on a tenant the user owns, and cannot remove the last owner', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);

    tenantsManager($this->owner)
        ->assertActionHidden(TestAction::make('makeOwner')->table($this->tenant))
        ->assertActionHidden(TestAction::make('manageTenantRoles')->table($this->tenant))
        ->assertActionHidden(TestAction::make('removeOwner')->table($this->tenant));
});

it('removes an owner from the admin portal once another owner exists', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);
    app(AddTenantOwner::class)->handle($this->tenant, $this->member);

    tenantsManager($this->member)
        ->callAction(TestAction::make('removeOwner')->table($this->tenant))
        ->assertNotified();

    expect($this->member->fresh()->isOwner($this->tenant))->toBeFalse()
        ->and($this->owner->fresh()->isOwner($this->tenant))->toBeTrue();
});

it('sets several roles for a member inside that tenant only', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
    app(AssignTenantRole::class)->handle($this->member, $second, TenantRoles::ADMIN);

    tenantsManager($this->member)
        ->callAction(TestAction::make('manageTenantRoles')->table($this->tenant), ['roles' => [TenantRoles::ADMIN->value, TenantRoles::VIEW_ONLY->value]])
        ->assertNotified();

    $roles = new UserTenantRoles($this->member->fresh());

    expect($roles->for($this->tenant)->sort()->values()->all())->toBe([TenantRoles::ADMIN->value, TenantRoles::VIEW_ONLY->value])
        ->and($roles->for($second)->all())->toBe([TenantRoles::ADMIN->value]);
});

it('hides the tenants tab from an admin who may not view tenants', function () {
    $admin = Admin::factory()->create();
    Permission::findOrCreate(AdminPermissions::USER_VIEW->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo(AdminPermissions::USER_VIEW);
    $this->actingAs($admin, AuthGuard::ADMIN->value);

    expect(TenantsRelationManager::canViewForRecord($this->member, ViewUser::class))->toBeFalse();
});

it('maps the tenant row actions to the seeded roles', function (AdminRoles $role, bool $canManage) {
    $this->seed(AdminRolePermissionsSeeder::class);
    $actor = Admin::factory()->create();
    $actor->assignRole($role);
    $this->actingAs($actor, AuthGuard::ADMIN->value);

    $manager = tenantsManager($this->member)->assertActionVisible(TestAction::make('openTenant')->table($this->tenant));

    $canManage
        ? $manager->assertActionVisible(TestAction::make('makeOwner')->table($this->tenant))->assertActionVisible(TestAction::make('manageTenantRoles')->table($this->tenant))
        : $manager->assertActionHidden(TestAction::make('makeOwner')->table($this->tenant))->assertActionHidden(TestAction::make('manageTenantRoles')->table($this->tenant));
})->with([
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
    'admin' => [AdminRoles::ADMIN, true],
    'support' => [AdminRoles::SUPPORT, false],
]);

function tenantUsersManager(Tenant $tenant)
{
    return Livewire::test(UsersRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => ViewTenant::class]);
}

it('lets only super admins and admins manage the roles from the tenant users tab', function (AdminRoles $role, bool $canManage) {
    $this->seed(AdminRolePermissionsSeeder::class);
    $actor = Admin::factory()->create();
    $actor->assignRole($role);
    $this->actingAs($actor, AuthGuard::ADMIN->value);

    $manager = tenantUsersManager($this->tenant);

    $canManage
        ? $manager->assertActionVisible(TestAction::make('manageTenantRoles')->table($this->member))
        : $manager->assertActionHidden(TestAction::make('manageTenantRoles')->table($this->member));
})->with([
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
    'admin' => [AdminRoles::ADMIN, true],
    'support' => [AdminRoles::SUPPORT, false],
]);

it('replaces the roles of a tenant user from the tenant users tab and keeps the owner role of owners', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::VIEW_ONLY);

    tenantUsersManager($this->tenant)
        ->callAction(TestAction::make('manageTenantRoles')->table($this->member), ['roles' => [TenantRoles::ADMIN->value]])
        ->assertNotified()
        ->assertActionHidden(TestAction::make('manageTenantRoles')->table($this->owner));

    expect((new UserTenantRoles($this->member->fresh()))->for($this->tenant)->all())->toBe([TenantRoles::ADMIN->value]);

    app(SyncTenantRoles::class)->handle($this->owner, $this->tenant, [TenantRoles::VIEW_ONLY]);

    expect((new UserTenantRoles($this->owner->fresh()))->for($this->tenant)->sort()->values()->all())->toBe([TenantRoles::OWNER->value, TenantRoles::VIEW_ONLY->value]);
});

it('offers remove from tenant only for non owner rows of a user with several tenants', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);

    tenantsManager($this->member)->assertActionHidden(TestAction::make('removeFromTenant')->table($this->tenant));
    tenantUsersManager($this->tenant)->assertActionHidden(TestAction::make('removeFromTenant')->table($this->member));

    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => false]);

    tenantsManager($this->member->fresh())->assertActionVisible(TestAction::make('removeFromTenant')->table($this->tenant));
    tenantUsersManager($this->tenant)
        ->assertActionVisible(TestAction::make('removeFromTenant')->table($this->member))
        ->assertActionHidden(TestAction::make('removeFromTenant')->table($this->owner));

    tenantUsersManager($this->tenant)
        ->callAction(TestAction::make('removeFromTenant')->table($this->member))
        ->assertNotified();

    expect($this->member->fresh()->tenants()->pluck('tenants.id')->all())->toBe([$second->getKey()]);
});

it('hides remove from tenant for an admin without update permission', function () {
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => false]);
    tenantsActor();

    tenantUsersManager($this->tenant)->assertActionHidden(TestAction::make('removeFromTenant')->table($this->member));
});
