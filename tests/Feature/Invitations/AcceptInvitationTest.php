<?php

use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Invitation\AcceptInvitation;
use Slakbal\TenancyFoundation\Actions\Invitation\CreateInvitation;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationAccepted;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

function invitedTo(Tenant $tenant, array $attributes = []): Invitation
{
    return Invitation::factory()->create(['tenant_id' => $tenant->getKey(), 'email' => 'new@example.test', ...$attributes]);
}

test('a new person gets a verified account, the membership and the role, and the invitation is closed', function () {
    Event::fake([InvitationAccepted::class]);
    $tenant = Tenant::factory()->create();
    $invitation = invitedTo($tenant);

    $user = app(AcceptInvitation::class)->handle($invitation, ['name' => 'New Person', 'password' => 'secret-password']);

    setPermissionsTeamId($tenant->getKey());

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->tenants->pluck('id')->all())->toBe([$tenant->getKey()])
        ->and($user->hasRole(TenantRoles::VIEW_ONLY))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    Event::assertDispatched(InvitationAccepted::class, fn ($event): bool => $event->user->is($user) && $event->invitation->is($invitation));
});

test('an invitation that was accepted, expired or whose account was deleted is refused and nothing changes', function (Closure $arrange) {
    $tenant = Tenant::factory()->create();
    $invitation = $arrange($tenant);

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, ['name' => 'X', 'password' => 'secret-password']))
        ->toThrow(InvalidInvitation::class)
        ->and($tenant->members()->count())->toBe(0);
})->with([
    'already accepted' => [fn (Tenant $tenant): Invitation => invitedTo($tenant, ['accepted_at' => now()->subDay()])],
    'expired' => [fn (Tenant $tenant): Invitation => invitedTo($tenant, ['expires_at' => now()->subMinute()])],
    'account was deleted' => [function (Tenant $tenant): Invitation {
        User::factory()->create(['email' => 'new@example.test'])->delete();

        return invitedTo($tenant);
    }],
]);

test('accepting twice only attaches the person once', function () {
    $tenant = Tenant::factory()->create();
    $invitation = invitedTo($tenant);
    $data = ['name' => 'New Person', 'password' => 'secret-password'];

    app(AcceptInvitation::class)->handle($invitation, $data);

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, $data))->toThrow(InvalidInvitation::class)
        ->and($tenant->members()->count())->toBe(1);
});

test('an existing user is attached without a new account and keeps their default tenant', function () {
    $existing = User::factory()->create(['email' => 'new@example.test']);
    $first = Tenant::factory()->create();
    $first->members()->attach($existing, ['is_owner' => false, 'is_default_tenant' => true]);
    $second = Tenant::factory()->create();

    $user = app(AcceptInvitation::class)->handle(invitedTo($second));

    expect($user->is($existing))->toBeTrue()
        ->and($existing->tenants()->wherePivot('is_default_tenant', true)->pluck('tenants.id')->all())->toBe([$first->getKey()])
        ->and($second->members()->count())->toBe(1);
});

test('the owner role cannot be given and a current member cannot be invited', function () {
    $tenant = Tenant::factory()->create();
    $inviter = User::factory()->create();
    $owner = Role::findOrCreate(TenantRoles::OWNER->value, AuthGuard::TENANT->value);
    $viewOnly = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $member = User::factory()->create(['email' => 'member@example.test']);
    $tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    expect(fn () => app(CreateInvitation::class)->handle($tenant, $inviter, 'someone@example.test', $owner))->toThrow(InvalidInvitation::class)
        ->and(fn () => app(CreateInvitation::class)->handle($tenant, $inviter, 'Member@Example.test', $viewOnly))->toThrow(InvalidInvitation::class)
        ->and(Invitation::query()->count())->toBe(0);
});

test('an invitation can be issued without an inviter and never serialises its token', function () {
    Event::fake([InvitationIssued::class]);
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    $invitation = app(CreateInvitation::class)->handle(Tenant::factory()->create(), null, 'someone@example.test', $role);

    expect($invitation->invited_by)->toBeNull()
        ->and($invitation->toArray())->not->toHaveKeys(['token', 'invited_by']);
});
