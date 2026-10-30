<?php

use Illuminate\Auth\Access\AuthorizationException;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantOwner;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => true]);
});

test('adding an owner keeps the existing owners and gives the new one the owner role', function () {
    app(AddTenantOwner::class)->handle($this->tenant, $this->member, $this->owner);

    expect($this->tenant->owners()->pluck('users.id')->sort()->values()->all())->toBe(collect([$this->owner->getKey(), $this->member->getKey()])->sort()->values()->all())
        ->and((new UserTenantRoles($this->member->fresh()))->for($this->tenant)->all())->toContain(TenantRoles::OWNER->value);
});

test('only an owner can add an owner, and only members who are not yet owners', function () {
    $outsider = User::factory()->create();

    expect(fn () => app(AddTenantOwner::class)->handle($this->tenant, $this->member, $this->member))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AddTenantOwner::class)->handle($this->tenant, $outsider, $this->owner))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(AddTenantOwner::class)->handle($this->tenant, $this->owner, $this->owner))->toThrow(InvalidArgumentException::class);
});

test('removing an owner demotes them to admin and never leaves a tenant without an owner', function () {
    app(AddTenantOwner::class)->handle($this->tenant, $this->member);

    app(RemoveTenantOwner::class)->handle($this->tenant, $this->member, $this->owner);

    expect($this->member->fresh()->isOwner($this->tenant))->toBeFalse()
        ->and((new UserTenantRoles($this->member->fresh()))->for($this->tenant)->all())->toBe([TenantRoles::ADMIN->value]);

    expect(fn () => app(RemoveTenantOwner::class)->handle($this->tenant, $this->owner, $this->owner))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveTenantOwner::class)->handle($this->tenant, $this->owner))->toThrow(AuthorizationException::class);
});

test('an owner can step down once another owner exists but a non owner cannot remove owners', function () {
    app(AddTenantOwner::class)->handle($this->tenant, $this->member);

    expect(fn () => app(RemoveTenantOwner::class)->handle($this->tenant, $this->owner, User::factory()->create()))->toThrow(AuthorizationException::class);

    app(RemoveTenantOwner::class)->handle($this->tenant, $this->owner, $this->owner);

    expect($this->tenant->owners()->pluck('users.id')->all())->toBe([$this->member->getKey()]);
});
