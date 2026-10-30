<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\ChangeTenantMemberStatus;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Globex', 'subdomain' => 'globex'], User::factory()->create());
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    $this->other->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => false]);
});

function actingInTenantAs(User $user, $tenant): void
{
    test()->actingAs($user, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);
}

function memberWithPermissions($tenant, TenantPermissions ...$permissions): User
{
    $user = User::factory()->create();
    $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function memberWithUpdatePermission($tenant): User
{
    $user = memberWithPermissions($tenant, TenantPermissions::USER_VIEW_ANY, TenantPermissions::USER_VIEW, TenantPermissions::USER_UPDATE);

    return $user;
}

test('deactivating a member changes only this tenant and leaves their other tenants alone', function () {
    app(ChangeTenantMemberStatus::class)->handle($this->tenant, $this->member, MemberStatus::INACTIVE, $this->owner);

    $member = User::query()->find($this->member->getKey());

    expect($member->memberStatus($this->tenant))->toBe(MemberStatus::INACTIVE)
        ->and($member->memberStatus($this->other))->toBe(MemberStatus::ACTIVE)
        ->and($member->canAccessTenant($this->tenant))->toBeFalse()
        ->and($member->canAccessTenant($this->other))->toBeTrue()
        ->and($member->getTenants(Filament::getCurrentPanel())->pluck('id')->all())->toBe([$this->other->getKey()]);

    app(ChangeTenantMemberStatus::class)->handle($this->tenant, $member, MemberStatus::ACTIVE, $this->owner);

    expect(User::query()->find($this->member->getKey())->canAccessTenant($this->tenant))->toBeTrue();
});

test('nobody can change their own status or the status of an owner', function () {
    app(AddTenantOwner::class)->handle($this->tenant, $this->member, $this->owner);

    expect(fn () => app(ChangeTenantMemberStatus::class)->handle($this->tenant, $this->owner, MemberStatus::INACTIVE, $this->owner))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ChangeTenantMemberStatus::class)->handle($this->tenant, $this->member, MemberStatus::INACTIVE, $this->owner))
        ->toThrow(AuthorizationException::class);

    expect($this->owner->fresh()->memberStatus($this->tenant))->toBe(MemberStatus::ACTIVE);
});

test('a user whose only active tenant is deactivated cannot use the panel', function () {
    $solo = User::factory()->create();
    $this->tenant->members()->attach($solo->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);

    app(ChangeTenantMemberStatus::class)->handle($this->tenant, $solo, MemberStatus::INACTIVE, $this->owner);
    $solo = $solo->fresh();

    expect($solo->hasNoAccessibleTenants())->toBeTrue()
        ->and($solo->canAccessPanel(Filament::getPanel('app')))->toBeFalse();
});

test('a signed in user whose access was deactivated is signed out', function () {
    $solo = User::factory()->create();
    $this->tenant->members()->attach($solo->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    app(ChangeTenantMemberStatus::class)->handle($this->tenant, $solo, MemberStatus::INACTIVE, $this->owner);

    $this->actingAs($solo->fresh(), AuthGuard::TENANT->value);
    $this->get($this->tenantUrl($this->tenant, '/'));

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('owners and admins can deactivate and activate members from the table, never themselves or an owner', function (string $actor) {
    $admin = memberWithUpdatePermission($this->tenant);
    $user = $actor === 'an owner' ? $this->owner : $admin;
    actingInTenantAs($user, $this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('deactivate', $user)
        ->assertTableActionHidden('deactivate', $this->owner)
        ->assertTableActionVisible('deactivate', $this->member)
        ->assertTableActionHidden('activate', $this->member)
        ->callTableAction('deactivate', $this->member)
        ->assertNotified()
        ->assertTableActionHidden('deactivate', $this->member)
        ->assertTableActionVisible('activate', $this->member)
        ->callTableAction('activate', $this->member)
        ->assertTableActionVisible('deactivate', $this->member);

    expect($this->member->fresh()->memberStatus($this->tenant))->toBe(MemberStatus::ACTIVE);
})->with(['an owner', 'an admin']);

test('a member without the update permission has no status actions', function () {
    $viewer = memberWithPermissions($this->tenant, TenantPermissions::USER_VIEW_ANY, TenantPermissions::USER_VIEW);
    actingInTenantAs($viewer, $this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('deactivate', $this->member);
});

test('the edit page offers the status actions and the status filter finds inactive members', function () {
    actingInTenantAs($this->owner, $this->tenant);

    Livewire::test(EditUser::class, ['record' => $this->member->getRouteKey()])
        ->assertActionVisible('deactivate')
        ->callAction('deactivate')
        ->assertActionVisible('activate')
        ->assertActionHidden('deactivate');

    Livewire::test(ListUsers::class)
        ->filterTable('membership_status', MemberStatus::INACTIVE->value)
        ->assertCanSeeTableRecords([$this->member])
        ->assertCanNotSeeTableRecords([$this->owner]);
});
