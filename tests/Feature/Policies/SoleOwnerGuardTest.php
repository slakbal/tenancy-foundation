<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\SoleOwnerGuard;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create(['name' => 'Jane Owner']);
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
});

test('the guard only reacts to delete and forceDelete', function (string $ability, bool $denied) {
    expect(SoleOwnerGuard::denial($ability, $this->owner) instanceof Response)->toBe($denied);
})->with([
    'delete' => ['delete', true],
    'forceDelete' => ['forceDelete', true],
    'update' => ['update', false],
    'view' => ['view', false],
    'restore' => ['restore', false],
    'deleteAny' => ['deleteAny', false],
]);

test('the guard accepts the arguments as an array', function () {
    expect(SoleOwnerGuard::denial('delete', [$this->owner]))->toBeInstanceOf(Response::class);
});

test('the guard ignores arguments that are not a model that can own a tenant', function () {
    expect(SoleOwnerGuard::denial('delete', User::class))->toBeNull()
        ->and(SoleOwnerGuard::denial('delete', []))->toBeNull()
        ->and(SoleOwnerGuard::denial('delete', null))->toBeNull()
        ->and(SoleOwnerGuard::denial('delete', $this->tenant))->toBeNull();
});

test('the denial message names the user from the configured name attributes', function () {
    config(['tenancy-foundation.user_name_attributes' => ['first_name', 'last_name']]);
    $user = (new User)->forceFill(['id' => $this->owner->getKey(), 'first_name' => 'Jane', 'last_name' => 'Owner']);

    expect(SoleOwnerGuard::denial('delete', $user)->message())->toContain('Jane Owner');
});

test('the denial message falls back to the email when no name is set', function () {
    config(['tenancy-foundation.user_name_attributes' => ['first_name', 'last_name']]);

    expect(SoleOwnerGuard::denial('delete', $this->owner)->message())->toContain($this->owner->email);
});

test('the preloaded sole owner flag agrees with the fallback query for every kind of user', function () {
    $coOwned = User::factory()->create();
    $coOwnedTenant = app(CreateTenant::class)->handle(['name' => 'Duo', 'subdomain' => 'duo'], $coOwned);
    $coOwnedTenant->members()->attach(User::factory()->create()->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    $trashedCoOwner = User::factory()->create();
    $trashedTenant = app(CreateTenant::class)->handle(['name' => 'Ghost', 'subdomain' => 'ghost'], $trashedCoOwner);
    $ghost = User::factory()->create();
    $trashedTenant->members()->attach($ghost->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);
    $ghost->delete();

    $users = User::query()->withSoleOwnerFlag()->get();

    expect($users)->not->toBeEmpty();

    $users->each(function (User $flagged): void {
        expect($flagged->isSoleOwnerOfAnyTenant())->toBe(User::find($flagged->getKey())->isSoleOwnerOfAnyTenant());
    });

    expect($users->firstWhere('id', $this->owner->getKey())->isSoleOwnerOfAnyTenant())->toBeTrue()
        ->and($users->firstWhere('id', $this->member->getKey())->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($users->firstWhere('id', $coOwned->getKey())->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($users->firstWhere('id', $trashedCoOwner->getKey())->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('the sole owner check works for a soft deleted user', function () {
    $this->owner->delete();

    expect(User::withTrashed()->find($this->owner->getKey())->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('the flag is added in a single query for the whole list', function () {
    User::factory()->count(5)->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    User::query()->withSoleOwnerFlag()->get()->each->isSoleOwnerOfAnyTenant();

    expect($queries)->toBe(1);
});
