<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Policies\Tenant\TenantPolicy;
use Slakbal\TenancyFoundation\Policies\Tenant\UserPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();
});

// Overriding one ability must keep the override's parameters untyped,
// since PHP forbids a subclass narrowing an untyped parent parameter to a
// typed one; AuthorisationPolicy's abilities are untyped specifically so
// concrete policies like this remain free to add extra logic.
test('a concrete policy can override update to add tenant-ownership logic beyond the permission map', function () {
    $policy = new class extends TenantPolicy
    {
        public function update($user, $tenant): bool
        {
            if (! $user->can(TenantPermissions::TENANT_UPDATE)) {
                return false;
            }

            return $tenant->members()
                ->where('users.id', $user->getKey())
                ->wherePivot('is_owner', true)
                ->exists();
        }
    };

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    Permission::findOrCreate(TenantPermissions::TENANT_UPDATE->value, AuthGuard::TENANT->value);

    $owner = User::factory()->create();
    $owner->givePermissionTo(TenantPermissions::TENANT_UPDATE);
    $tenant->members()->attach($owner, ['is_owner' => true, 'is_default_tenant' => true]);

    $member = User::factory()->create();
    $member->givePermissionTo(TenantPermissions::TENANT_UPDATE);
    $tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    expect($policy->update($owner, $tenant))->toBeTrue();
    expect($policy->update($member, $tenant))->toBeFalse();
});

test('a concrete policy can override delete to add a self-lockout guard', function () {
    $policy = new class extends UserPolicy
    {
        public function delete($actor, $target): bool
        {
            if ($actor->is($target)) {
                return false;
            }

            return $actor->can(TenantPermissions::USER_DELETE);
        }
    };

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    Permission::findOrCreate(TenantPermissions::USER_DELETE->value, AuthGuard::TENANT->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(TenantPermissions::USER_DELETE);
    $tenant->members()->attach($actor, ['is_owner' => true, 'is_default_tenant' => true]);

    $otherUser = User::factory()->create();

    expect($policy->delete($actor, $otherUser))->toBeTrue();
    expect($policy->delete($actor, $actor))->toBeFalse();
});

test('the real Gate dispatches to an overridden ability, not the base permission-map method', function () {
    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    Permission::findOrCreate(TenantPermissions::USER_DELETE->value, AuthGuard::TENANT->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(TenantPermissions::USER_DELETE);
    $tenant->members()->attach($actor, ['is_owner' => true, 'is_default_tenant' => true]);

    $override = new class extends UserPolicy
    {
        public function delete($actor, $target): bool
        {
            return ! $actor->is($target) && $actor->can(TenantPermissions::USER_DELETE);
        }
    };

    Gate::policy(User::class, $override::class);
    app()->instance($override::class, $override);

    expect($actor->can('delete', $actor))->toBeFalse();
    expect($actor->can('delete', User::factory()->create()))->toBeTrue();
});
