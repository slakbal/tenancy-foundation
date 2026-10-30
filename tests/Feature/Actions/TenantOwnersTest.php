<?php

use Illuminate\Auth\Access\AuthorizationException;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
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

test('removing a member from one of several tenants detaches only that tenant and repairs the default', function () {
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], User::factory()->create());
    $second->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
    app(AssignTenantRole::class)->handle($this->member, $second, TenantRoles::VIEW_ONLY);

    app(RemoveTenantMember::class)->handle($this->tenant, $this->member);

    $member = $this->member->fresh();
    $roles = new UserTenantRoles($member);

    expect($member->tenants()->pluck('tenants.id')->all())->toBe([$second->getKey()])
        ->and((bool) $member->tenants()->first()->pivot->is_default_tenant)->toBeTrue()
        ->and($roles->for($this->tenant)->all())->toBe([])
        ->and($roles->for($second)->all())->toBe([TenantRoles::VIEW_ONLY->value]);
});

test('a member cannot be removed from their only tenant, an owner never, and a stranger not at all', function () {
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
    $other->members()->attach($this->owner, ['is_owner' => false, 'is_default_tenant' => false]);

    expect(fn () => app(RemoveTenantMember::class)->handle($this->tenant, $this->member))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveTenantMember::class)->handle($this->tenant, $this->owner))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveTenantMember::class)->handle($other, $this->member))->toThrow(InvalidArgumentException::class)
        ->and($this->member->fresh()->tenants()->count())->toBe(1)
        ->and($this->owner->fresh()->isOwner($this->tenant))->toBeTrue();
});
