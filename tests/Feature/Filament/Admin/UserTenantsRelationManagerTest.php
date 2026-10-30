<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\RelationManagers\TenantsRelationManager;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;
use Spatie\Permission\Models\Permission;

function tenantsActor(AdminPermissions ...$permissions): Admin
{
    $admin = Admin::factory()->create();

    foreach ([AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_VIEW, AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, ...$permissions] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

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

it('makes a member the owner of the tenant', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);

    tenantsManager($this->member)
        ->callAction(TestAction::make('makeOwner')->table($this->tenant))
        ->assertNotified();

    expect($this->member->isOwner($this->tenant))->toBeTrue()
        ->and($this->owner->fresh()->isOwner($this->tenant))->toBeFalse();
});

it('offers neither make owner nor a role change on a tenant the user owns', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);

    tenantsManager($this->owner)
        ->assertActionHidden(TestAction::make('makeOwner')->table($this->tenant))
        ->assertActionHidden(TestAction::make('changeTenantRole')->table($this->tenant));
});

it('changes the role of a member inside that tenant only', function () {
    tenantsActor(AdminPermissions::USER_UPDATE);
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
    app(AssignTenantRole::class)->handle($this->member, $second, TenantRoles::ADMIN);

    tenantsManager($this->member)
        ->callAction(TestAction::make('changeTenantRole')->table($this->tenant), ['role' => TenantRoles::VIEW_ONLY->value])
        ->assertNotified();

    $roles = new UserTenantRoles($this->member->fresh());

    expect($roles->for($this->tenant)->all())->toBe([TenantRoles::VIEW_ONLY->value])
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
        ? $manager->assertActionVisible(TestAction::make('makeOwner')->table($this->tenant))->assertActionVisible(TestAction::make('changeTenantRole')->table($this->tenant))
        : $manager->assertActionHidden(TestAction::make('makeOwner')->table($this->tenant))->assertActionHidden(TestAction::make('changeTenantRole')->table($this->tenant));
})->with([
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
    'admin' => [AdminRoles::ADMIN, true],
    'support' => [AdminRoles::SUPPORT, false],
]);
