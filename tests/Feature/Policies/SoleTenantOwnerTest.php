<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

function createTenantOwnedBy(User $owner, string $subdomain): Tenant
{
    return app(CreateTenant::class)->handle(['name' => ucfirst($subdomain), 'subdomain' => $subdomain], $owner);
}

function attachTenantMember(Tenant $tenant, ?User $user = null, bool $isOwner = false): User
{
    $user ??= User::factory()->create();
    $tenant->members()->attach($user->getKey(), ['is_owner' => $isOwner, 'is_default_tenant' => true]);

    return $user;
}

function transferTenantOwnershipAs(Tenant $tenant, User $newOwner, User $actor): void
{
    app(TransferTenantOwnership::class)->handle($tenant, $newOwner, $actor);
    setPermissionsTeamId($tenant->getKey());
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = createTenantOwnedBy($this->owner, 'acme');
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = attachTenantMember($this->tenant);
});

test('a sole owner cannot be deleted even though the owner role bypasses policies', function () {
    Filament::setTenant($this->tenant, isQuiet: true);

    expect($this->owner->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->owner->isSoleOwnerOfAnyTenant())->toBeTrue()
        ->and($this->owner->can('delete', $this->owner))->toBeFalse()
        ->and($this->owner->can('forceDelete', $this->owner))->toBeFalse();
});

test('a member who owns nothing is not blocked and can be deleted', function () {
    Filament::setTenant($this->tenant, isQuiet: true);
    expect($this->member->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($this->owner->can('delete', $this->member))->toBeTrue();
});

test('transferring ownership makes the new owner the only owner', function () {
    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()])
        ->and($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->owner->fresh()->hasRole(TenantRoles::ADMIN))->toBeTrue()
        ->and($this->member->fresh()->isSoleOwnerOfAnyTenant())->toBeTrue();
});

test('the previous owner stays a member and can be deleted after the transfer', function () {
    Filament::setTenant($this->tenant, isQuiet: true);
    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    $previous = $this->owner->fresh();

    expect($this->tenant->members()->where('users.id', $previous->getKey())->exists())->toBeTrue()
        ->and($previous->isSoleOwnerOfAnyTenant())->toBeFalse()
        ->and($this->member->fresh()->can('delete', $previous))->toBeTrue();
});

test('every previous owner is demoted when ownership is transferred', function () {
    $coOwner = attachTenantMember($this->tenant, null, isOwner: true);

    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    expect($this->tenant->members()->wherePivot('is_owner', true)->count())->toBe(1)
        ->and($this->tenant->members()->wherePivot('is_owner', true)->first()->getKey())->toBe($this->member->getKey())
        ->and($coOwner->fresh()->isSoleOwnerOfAnyTenant())->toBeFalse();
});

test('an owner with a live legacy co-owner can be deleted', function () {
    attachTenantMember($this->tenant, null, isOwner: true);
    Filament::setTenant($this->tenant, isQuiet: true);

    expect($this->owner->can('delete', $this->owner))->toBeTrue();
});

test('ownership of another tenant decides whether the previous owner stays a sole owner', function (bool $coOwned) {
    $second = createTenantOwnedBy($this->owner, 'second');

    if ($coOwned) {
        attachTenantMember($second, null, isOwner: true);
    }

    expect($this->owner->isSoleOwnerOfAnyTenant())->toBeTrue();

    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    expect($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBe(! $coOwned)
        ->and($this->owner->fresh()->ownedTenants->pluck('id')->all())->toBe([$second->getKey()]);
})->with([
    'second tenant co-owned' => true,
    'second tenant solo' => false,
]);

test('only an owner of the tenant can transfer it and nothing changes when refused', function (string $actorKind) {
    $stranger = User::factory()->create();
    $target = attachTenantMember($this->tenant);

    match ($actorKind) {
        'a previous owner' => transferTenantOwnershipAs($this->tenant, $this->member, $this->owner),
        'an owner of a different tenant' => createTenantOwnedBy($stranger, 'elsewhere'),
        default => null,
    };

    $actor = match ($actorKind) {
        'a member' => $this->member,
        'an owner of a different tenant' => $stranger,
        'a previous owner' => $this->owner->fresh(),
    };

    $owners = fn (): array => $this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all();
    $before = $owners();

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $target, $actor))
        ->toThrow(AuthorizationException::class);

    setPermissionsTeamId($this->tenant->getKey());

    expect($owners())->toBe($before)
        ->and($target->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse();
})->with(['a member', 'an owner of a different tenant', 'a previous owner']);

test('ownership can only be transferred to another live member of the tenant', function (string $targetKind) {
    $target = match ($targetKind) {
        'themselves' => $this->owner,
        'a user outside the tenant' => User::factory()->create(),
        'a soft deleted member' => tap($this->member)->delete(),
    };

    $members = fn (): array => $this->tenant->members()->pluck('users.id')->sort()->values()->all();
    $before = $members();

    expect(fn () => app(TransferTenantOwnership::class)->handle($this->tenant, $target, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    expect($members())->toBe($before)
        ->and($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue()
        ->and($this->owner->fresh()->isSoleOwnerOfAnyTenant())->toBeTrue();
})->with(['themselves', 'a user outside the tenant', 'a soft deleted member']);

test('roles are scoped to the tenant the ownership moved in', function () {
    $second = Tenant::factory()->create();
    attachTenantMember($second, $this->member);

    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    setPermissionsTeamId($second->getKey());
    expect($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->member->fresh()->ownedTenants->contains($second))->toBeFalse();

    setPermissionsTeamId($this->tenant->getKey());
    expect($this->member->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue();
});

test('a soft deleted previous owner is demoted too and does not return as an owner when restored', function () {
    $ghost = attachTenantMember($this->tenant, null, isOwner: true);
    $ghost->delete();

    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    $ghost->restore();

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()]);
});

test('the membership timestamps are touched for the demoted owner', function () {
    $this->travel(1)->hour();

    transferTenantOwnershipAs($this->tenant, $this->member, $this->owner);

    $pivot = $this->tenant->members()->where('users.id', $this->owner->getKey())->first()->pivot;

    expect($pivot->updated_at->greaterThan($pivot->created_at))->toBeTrue();
});
