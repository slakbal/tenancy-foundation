<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

function memberOf($tenant, array $permissions = [], bool $owner = false): User
{
    $user = User::factory()->create();
    $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => $owner, 'is_default_tenant' => true]]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        $user->givePermissionTo($permission);
    }

    return $user;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->single = memberOf($this->tenant);
    $this->shared = memberOf($this->tenant);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
    $this->other->members()->syncWithoutDetaching([$this->shared->getKey() => ['is_owner' => false, 'is_default_tenant' => false]]);

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('a user in only this tenant can be deleted but not removed, a shared user the other way round', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('delete', $this->single)
        ->assertTableActionHidden('removeFromTenant', $this->single)
        ->assertTableActionVisible('removeFromTenant', $this->shared)
        ->assertTableActionHidden('delete', $this->shared);
});

test('removing a shared user detaches only this tenant and the user is not deleted', function () {
    Livewire::test(ListUsers::class)
        ->callTableAction('removeFromTenant', $this->shared)
        ->assertNotified();

    $shared = User::withoutGlobalScopes()->find($this->shared->getKey());

    expect($shared->trashed())->toBeFalse()
        ->and($shared->tenants()->pluck('tenants.id')->all())->toBe([$this->other->getKey()]);
});

test('an owner of this tenant is never removed, even when they belong to other tenants, and nobody removes themselves', function () {
    $coOwner = memberOf($this->tenant, owner: true);
    $this->other->members()->syncWithoutDetaching([$coOwner->getKey() => ['is_owner' => false, 'is_default_tenant' => false]]);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('removeFromTenant', $coOwner)
        ->assertTableActionHidden('removeFromTenant', $this->owner);
});

test('bulk delete never soft deletes a user who belongs to another tenant', function () {
    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', [$this->single, $this->shared]);

    expect($this->single->fresh()->trashed())->toBeTrue()
        ->and($this->shared->fresh()->trashed())->toBeFalse();
});

test('an admin may remove a member but a view only member may not', function (array $permissions, bool $allowed) {
    $actor = memberOf($this->tenant, [TenantPermissions::USER_VIEW_ANY, ...$permissions]);
    $this->actingAs($actor, AuthGuard::TENANT->value);

    $list = Livewire::test(ListUsers::class);

    $allowed
        ? $list->assertTableActionVisible('removeFromTenant', $this->shared)
        : $list->assertTableActionHidden('removeFromTenant', $this->shared);
})->with([
    'with the delete permission' => [[TenantPermissions::USER_DELETE], true],
    'without it' => [[], false],
]);

test('a soft deleted user only offers restore and force delete', function () {
    $this->shared->delete();
    $this->single->delete();

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->assertTableActionVisible('restore', $this->single)
        ->assertTableActionVisible('forceDelete', $this->single)
        ->assertTableActionHidden('edit', $this->single)
        ->assertTableActionHidden('delete', $this->single)
        ->assertTableActionHidden('makeOwner', $this->single)
        ->assertTableActionHidden('removeFromTenant', $this->shared);
});
