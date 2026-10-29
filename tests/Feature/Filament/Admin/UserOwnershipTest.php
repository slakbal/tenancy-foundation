<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

    foreach ([AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_UPDATE] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    }

    $this->admin = Admin::factory()->create();
    $this->admin->givePermissionTo(AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_UPDATE);
    $this->actingAs($this->admin, AuthGuard::ADMIN->value);
});

test('an administrator can transfer a tenant to one of its members without being a member', function () {
    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member, ['tenant_id' => $this->tenant->getKey()])
        ->assertNotified();

    setPermissionsTeamId($this->tenant->getKey());

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()])
        ->and($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue();
});

test('the administrator account is left untouched by a transfer', function () {
    $roles = $this->admin->roles()->count();
    $permissions = $this->admin->getAllPermissions()->count();

    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member, ['tenant_id' => $this->tenant->getKey()]);

    $admin = $this->admin->fresh();

    expect($admin->roles()->count())->toBe($roles)
        ->and($admin->getAllPermissions()->count())->toBe($permissions)
        ->and(Admin::query()->whereKey($admin->getKey())->exists())->toBeTrue()
        ->and($this->tenant->members()->where('users.email', $admin->email)->exists())->toBeFalse();
});

test('the action is hidden for a user who is the owner of every tenant they belong to', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('makeOwner', $this->owner)
        ->assertTableActionVisible('makeOwner', $this->member);
});

test('the action is hidden for an administrator without the update permission', function () {
    $viewer = Admin::factory()->create();
    $viewer->givePermissionTo(AdminPermissions::USER_VIEW_ANY);
    $this->actingAs($viewer, AuthGuard::ADMIN->value);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('makeOwner', $this->member);
});

test('a tenant the user does not belong to cannot be chosen', function () {
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());

    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member, ['tenant_id' => $other->getKey()]);

    expect($other->members()->wherePivot('is_owner', true)->where('users.id', $this->member->getKey())->exists())->toBeFalse()
        ->and($this->owner->fresh()->ownedTenants->contains($this->tenant))->toBeTrue();
});

test('a member of several tenants only changes the tenant that was chosen', function () {
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => false]);

    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member, ['tenant_id' => $second->getKey()]);

    expect($this->member->fresh()->ownedTenants->pluck('id')->all())->toBe([$second->getKey()])
        ->and($this->owner->fresh()->ownedTenants->contains($this->tenant))->toBeTrue();
});

test('the administrator transfer refuses a user who already owns the tenant', function () {
    expect(fn () => app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $this->owner))
        ->toThrow(InvalidArgumentException::class);
});

test('the administrator transfer refuses a user who is not a member', function () {
    expect(fn () => app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, User::factory()->create()))
        ->toThrow(InvalidArgumentException::class);
});

test('the administrator transfer demotes every previous owner', function () {
    $coOwner = User::factory()->create();
    $this->tenant->members()->attach($coOwner->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $this->member);

    expect($this->tenant->members()->wherePivot('is_owner', true)->count())->toBe(1);
});
