<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

function registerTenantFor(User $owner, string $subdomain): Tenant
{
    return app(CreateTenant::class)->handle(['name' => ucfirst($subdomain), 'subdomain' => $subdomain], $owner);
}

function attachMember(Tenant $tenant, ?User $user = null, bool $isOwner = false): User
{
    $user ??= User::factory()->create();
    $tenant->members()->attach($user->getKey(), ['is_owner' => $isOwner, 'is_default_tenant' => true]);

    return $user;
}

function transferOwnership(Tenant $tenant, User $newOwner, User $actor): void
{
    app(TransferTenantOwnership::class)->handle($tenant, $newOwner, $actor);
    setPermissionsTeamId($tenant->getKey());
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = registerTenantFor($this->owner, 'acme');
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = attachMember($this->tenant);
});

test('a sole owner cannot be deleted even though the owner role bypasses policies', function () {
    expect($this->owner->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->owner->isSoleOwnerOfAnyTenant())->toBeTrue()
        ->and($this->owner->can('delete', $this->owner))->toBeFalse()
        ->and($this->owner->can('forceDelete', $this->owner))->toBeFalse();
});

test('a sole owner of a tenant with no other members is blocked too', function () {
    $loner = User::factory()->create();
    registerTenantFor($loner, 'solo');

    expect($loner->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('a member who owns nothing is not blocked and can be deleted', function () {
    Filament::setTenant($this->tenant, isQuiet: true);
    expect($this->member->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($this->owner->can('delete', $this->member))->toBeTrue();
});

test('the block only concerns deleting, other abilities are untouched', function () {
    Filament::setTenant($this->tenant, isQuiet: true);
    expect($this->owner->can('update', $this->owner))->toBeTrue()
        ->and($this->owner->can('view', $this->owner))->toBeTrue()
        ->and($this->owner->can('deleteAny', User::class))->toBeTrue();
});

test('transferring ownership makes the new owner the only owner', function () {
    transferOwnership($this->tenant, $this->member, $this->owner);

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()])
        ->and($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->owner->fresh()->hasRole(TenantRoles::ADMIN))->toBeTrue();
});

test('the previous owner stays a member and can be deleted after the transfer', function () {
    Filament::setTenant($this->tenant, isQuiet: true);
    transferOwnership($this->tenant, $this->member, $this->owner);

    $previous = $this->owner->fresh();

    expect($this->tenant->members()->where('users.id', $previous->getKey())->exists())->toBeTrue()
        ->and($previous->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($this->member->fresh()->can('delete', $previous))->toBeTrue();
});

test('the new owner is the one protected after the transfer', function () {
    transferOwnership($this->tenant, $this->member, $this->owner);

    $newOwner = $this->member->fresh();

    expect($newOwner->isSoleOwnerOfAnyTenant())->toBeTrue()
        ->and($newOwner->can('delete', $newOwner))->toBeFalse();
});

test('every previous owner is demoted when ownership is transferred', function () {
    $coOwner = attachMember($this->tenant, null, isOwner: true);

    transferOwnership($this->tenant, $this->member, $this->owner);

    expect($this->tenant->members()->wherePivot('is_owner', true)->count())->toBe(1)
        ->and($this->tenant->members()->wherePivot('is_owner', true)->first()->getKey())->toBe($this->member->getKey())
        ->and($coOwner->fresh()->isSoleOwnerOfAnyTenant())->toBeFalse();
});

test('a legacy co-owner who is soft deleted does not count as another owner', function () {
    $coOwner = attachMember($this->tenant, null, isOwner: true);
    $coOwner->delete();

    expect($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('a live legacy co-owner means nobody is the sole owner', function () {
    attachMember($this->tenant, null, isOwner: true);
    Filament::setTenant($this->tenant, isQuiet: true);

    expect($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($this->owner->can('delete', $this->owner))->toBeTrue();
});

test('being a sole owner of any one tenant is enough to block, even if co-owned elsewhere', function () {
    $second = registerTenantFor($this->owner, 'second');
    attachMember($second, null, isOwner: true);

    expect($this->owner->isSoleOwnerOfAnyTenant())->toBeTrue();

    transferOwnership($this->tenant, $this->member, $this->owner);

    expect($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeFalse();
});

test('co-ownership in a different tenant does not unblock the owner', function () {
    $other = registerTenantFor(User::factory()->create(), 'other');
    attachMember($other, null, isOwner: true);
    attachMember($other, $this->owner);

    expect($this->owner->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('only an owner of the tenant can transfer it and nothing changes when refused', function () {
    $other = attachMember($this->tenant);

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $other, $this->member))
        ->toThrow(AuthorizationException::class);

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->owner->getKey()])
        ->and($other->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse();
});

test('an owner of a different tenant cannot transfer this one', function () {
    $stranger = User::factory()->create();
    registerTenantFor($stranger, 'elsewhere');

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $this->member, $stranger))
        ->toThrow(AuthorizationException::class);
});

test('a previous owner cannot transfer the tenant again', function () {
    transferOwnership($this->tenant, $this->member, $this->owner);

    $third = attachMember($this->tenant);

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $third, $this->owner->fresh()))
        ->toThrow(AuthorizationException::class);
});

test('an owner cannot transfer the tenant to themselves', function () {
    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $this->owner, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    expect($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue();
});

test('only a member of the tenant can become the owner', function () {
    $stranger = User::factory()->create();

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $stranger, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    expect($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->tenant->members()->where('users.id', $stranger->getKey())->exists())->toBeFalse();
});

test('roles are scoped to the tenant the ownership moved in', function () {
    $second = Tenant::factory()->create();
    attachMember($second, $this->member);

    transferOwnership($this->tenant, $this->member, $this->owner);

    setPermissionsTeamId($second->getKey());
    expect($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->member->fresh()->ownedTenants->contains($second))->toBeFalse();

    setPermissionsTeamId($this->tenant->getKey());
    expect($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue();
});

test('the previous owner keeps ownership of any other tenant they own', function () {
    $second = registerTenantFor($this->owner, 'second');

    transferOwnership($this->tenant, $this->member, $this->owner);

    expect($this->owner->fresh()->ownedTenants->pluck('id')->all())->toBe([$second->getKey()])
        ->and($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('a soft deleted previous owner is demoted too and does not return as an owner when restored', function () {
    $ghost = attachMember($this->tenant, null, isOwner: true);
    $ghost->delete();

    transferOwnership($this->tenant, $this->member, $this->owner);

    $ghost->restore();

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()]);
});

test('a soft deleted member cannot become the owner', function () {
    $this->member->delete();

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $this->member, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    expect($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('the membership timestamps are touched for the demoted owner', function () {
    $this->travel(1)->hour();

    transferOwnership($this->tenant, $this->member, $this->owner);

    $pivot = $this->tenant->members()->where('users.id', $this->owner->getKey())->first()->pivot;

    expect($pivot->updated_at->greaterThan($pivot->created_at))->toBeTrue();
});
