<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
});

function actAsTenantUserIn(User $user, $tenant): void
{
    test()->actingAs($user, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);
}

test('the sole owner has no delete action while other users do', function () {
    actAsTenantUserIn($this->owner, $this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $this->owner)
        ->assertTableActionVisible('delete', $this->member);
});

test('make owner shows only to owners and only for non owner members', function (string $actor, bool $visibleForMember) {
    if ($actor === 'a non owner with every user permission') {
        foreach (TenantPermissions::cases() as $permission) {
            Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        }
        $this->member->givePermissionTo(TenantPermissions::cases());
    }

    actAsTenantUserIn($actor === 'an owner' ? $this->owner : $this->member, $this->tenant);

    $list = Livewire::test(ListUsers::class)
        ->assertTableActionHidden('makeOwner', $this->owner);

    $visibleForMember
        ? $list->assertTableActionVisible('makeOwner', $this->member)
        : $list->assertTableActionHidden('makeOwner', $this->member);
})->with([
    'an owner' => ['an owner', true],
    'a non owner with every user permission' => ['a non owner with every user permission', false],
]);

test('making a member the owner transfers ownership and demotes the previous owner', function () {
    actAsTenantUserIn($this->owner, $this->tenant);

    Livewire::test(ListUsers::class)
        ->callTableAction('makeOwner', $this->member)
        ->assertNotified();

    expect($this->member->fresh()->ownedTenants->contains($this->tenant))->toBeTrue()
        ->and($this->owner->fresh()->ownedTenants->contains($this->tenant))->toBeFalse()
        ->and($this->tenant->members()->wherePivot('is_owner', true)->count())->toBe(1);
});

test('after the hand over the original owner can be deleted but the new sole owner cannot', function () {
    app(TransferTenantOwnership::class)->handle($this->tenant, $this->member, $this->owner);
    actAsTenantUserIn($this->member, $this->tenant);

    Livewire::test(ListUsers::class)
        ->callTableAction('delete', $this->owner);

    expect($this->owner->fresh()->trashed())->toBeTrue();

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $this->member);
});

test('bulk delete skips a sole owner and deletes only the other selected users', function (bool $withMember) {
    actAsTenantUserIn($this->owner, $this->tenant);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', $withMember ? [$this->owner, $this->member] : [$this->owner])
        ->assertNotified();

    expect($this->owner->fresh()->trashed())->toBeFalse()
        ->and($this->member->fresh()->trashed())->toBe($withMember);
})->with([
    'the owner and another user' => true,
    'only the owner' => false,
]);

test('the edit page hides delete for a sole owner and shows it for others', function () {
    actAsTenantUserIn($this->owner, $this->tenant);

    Livewire::test(EditUser::class, ['record' => $this->owner->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::test(EditUser::class, ['record' => $this->member->getRouteKey()])
        ->assertActionVisible('delete');
});

test('bulk force delete also skips a sole owner', function () {
    $this->owner->delete();
    $this->member->delete();
    actAsTenantUserIn($this->owner, $this->tenant);

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->callTableBulkAction('forceDelete', [$this->owner, $this->member]);

    expect(User::withTrashed()->find($this->owner->getKey()))->not->toBeNull()
        ->and(User::withTrashed()->find($this->member->getKey()))->toBeNull();
});
