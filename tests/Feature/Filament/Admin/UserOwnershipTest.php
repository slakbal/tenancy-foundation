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

test('only the chosen tenant of the user changes', function (bool $belongs) {
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());

    if ($belongs) {
        $second->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => false]);
    }

    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member, ['tenant_id' => $second->getKey()]);

    expect($this->member->fresh()->ownedTenants->pluck('id')->all())->toBe($belongs ? [$second->getKey()] : [])
        ->and($this->owner->fresh()->ownedTenants->contains($this->tenant))->toBeTrue();
})->with([
    'a tenant the user belongs to' => true,
    'a tenant the user does not belong to' => false,
]);

test('the administrator transfer refuses a user who already owns the tenant or is not a member', function (string $candidate) {
    $user = $candidate === 'an owner' ? $this->owner : User::factory()->create();

    expect(fn () => app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $user))
        ->toThrow(InvalidArgumentException::class);
})->with(['an owner', 'a non member']);

test('the administrator transfer demotes every previous owner', function () {
    $coOwner = User::factory()->create();
    $this->tenant->members()->attach($coOwner->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $this->member);

    expect($this->tenant->members()->wherePivot('is_owner', true)->count())->toBe(1);
});
