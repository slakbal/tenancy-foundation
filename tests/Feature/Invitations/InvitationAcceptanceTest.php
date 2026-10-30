<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\AcceptInvitation;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

function invitationAcceptUrl(Invitation $invitation): string
{
    return URL::temporarySignedRoute(
        'filament.app.invitations.accept',
        $invitation->expires_at,
        ['invitation' => $invitation->getRouteKey()],
    );
}

test('visiting a valid invitation link shows the accept page with a registration form for a new email', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'new-person@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get(invitationAcceptUrl($invitation));

    $response->assertOk();
    $response->assertSee('wire:model="data.name"', false);
    $response->assertSee('wire:model="data.password"', false);
});

test('visiting a valid invitation link for an already-registered email skips the registration fields', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'existing@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    Tenant::factory()->create()->members()->attach($existing, ['is_owner' => false, 'is_default_tenant' => true]);
    $this->actingAs($existing, AuthGuard::TENANT->value);

    $response = $this->get(invitationAcceptUrl($invitation));

    $response->assertOk();
    $response->assertSee('Accept invitation');
    $response->assertDontSee('wire:model="data.name"', false);
});

test('accepting a new-email invitation creates the user, attaches them to the tenant, and assigns the role', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::ADMIN->value, AuthGuard::TENANT->value);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'brand-new@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])
        ->set('data.name', 'Brand New')
        ->set('data.password', 'password123')
        ->set('data.passwordConfirmation', 'password123')
        ->call('accept');

    $user = User::where('email', 'brand-new@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($tenant->members()->where('users.id', $user->id)->exists())->toBeTrue();

    setPermissionsTeamId($tenant->getKey());
    expect($user->hasRole(TenantRoles::ADMIN))->toBeTrue();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user, AuthGuard::TENANT->value);
});

test('accepting an invitation for an existing user attaches them without requiring registration fields', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $existingUser = User::factory()->create(['email' => 'already-here@example.com']);

    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'already-here@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($existingUser, AuthGuard::TENANT->value);

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])
        ->call('accept');

    expect($tenant->members()->where('users.id', $existingUser->id)->exists())->toBeTrue();

    setPermissionsTeamId($tenant->getKey());
    expect($existingUser->hasRole(TenantRoles::VIEW_ONLY))->toBeTrue();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();
});

test('an existing account has to sign in first: a guest is sent to the login and the link is remembered', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'already-here@example.com', 'accepted_at' => null, 'expires_at' => now()->addDays(7)]);
    User::factory()->create(['email' => 'already-here@example.com']);

    $this->get(invitationAcceptUrl($invitation))->assertRedirect();

    $this->assertGuest(AuthGuard::TENANT->value);
    expect(session('url.intended'))->toContain('invitations/accept')
        ->and($tenant->members()->count())->toBe(0);
});

test('a guest who calls accept for an existing account is not logged in and not attached', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'already-here@example.com', 'accepted_at' => null, 'expires_at' => now()->addDays(7)]);
    User::factory()->create(['email' => 'already-here@example.com']);

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])->call('accept');

    $this->assertGuest(AuthGuard::TENANT->value);
    expect($tenant->members()->count())->toBe(0)
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

test('a user signed in as someone else sees a notice and cannot accept an invitation for another account', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'already-here@example.com', 'accepted_at' => null, 'expires_at' => now()->addDays(7)]);
    User::factory()->create(['email' => 'already-here@example.com']);
    $this->actingAs(User::factory()->create(), AuthGuard::TENANT->value);

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])
        ->assertSee(__('This invitation is for another account'))
        ->call('accept');

    expect($tenant->members()->count())->toBe(0);
});

test('an already-accepted invitation shows the already-accepted message and cannot be accepted twice', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $user = User::factory()->create(['email' => 'already-accepted@example.com']);
    $acceptedAt = now()->subHour()->startOfSecond();
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'already-accepted@example.com',
        'accepted_at' => $acceptedAt,
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(invitationAcceptUrl($invitation))
        ->assertOk()
        ->assertSee('This invitation was already accepted');

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])
        ->call('accept')
        ->assertNoRedirect();

    expect($tenant->members()->where('users.id', $user->id)->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at->equalTo($acceptedAt))->toBeTrue();
    $this->assertGuest(AuthGuard::TENANT->value);
});

test('an expired invitation shows the expired message and cannot be accepted', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $user = User::factory()->create(['email' => 'too-late@example.com']);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'too-late@example.com',
        'accepted_at' => null,
        'expires_at' => now()->subDay(),
    ]);

    // Build the signature against a future expiry so the signed-URL check
    // itself passes; the page's own expiry check must still reject it.
    $url = URL::temporarySignedRoute(
        'filament.app.invitations.accept',
        now()->addHour(),
        ['invitation' => $invitation->getRouteKey()],
    );

    $this->get($url)
        ->assertOk()
        ->assertSee('This invitation has expired');

    Livewire::test(AcceptInvitation::class, ['invitation' => $invitation])
        ->call('accept')
        ->assertNoRedirect();

    expect($tenant->members()->where('users.id', $user->id)->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull();
    $this->assertGuest(AuthGuard::TENANT->value);
});

test('a tampered invitation link fails signature verification', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $url = invitationAcceptUrl($invitation).'&tampered=1';

    $response = $this->get($url);

    $response->assertForbidden();
});

function acceptInvitationQueryCounts(Closure $callback): array
{
    $counts = ['tenant' => 0, 'role' => 0, 'user' => 0];

    DB::listen(function ($query) use (&$counts): void {
        foreach ([
            'tenant' => 'select * from "tenants" where "tenants"."id" = ?',
            'role' => 'select * from "roles" where "roles"."id" = ?',
            'user' => 'select * from "users" where "email" = ?',
        ] as $key => $prefix) {
            if (str_starts_with($query->sql, $prefix)) {
                $counts[$key]++;
            }
        }
    });

    $callback();

    return $counts;
}

test('visiting the accept page loads the tenant and role at most once each', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'queries@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $counts = acceptInvitationQueryCounts(fn () => $this->get(invitationAcceptUrl($invitation))->assertOk());

    expect($counts['tenant'])->toBe(1)->and($counts['role'])->toBeLessThanOrEqual(1);
});

test('accepting an invitation loads the tenant, role and user once each', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $existing = User::factory()->create(['email' => 'queries-existing@example.com']);
    $this->actingAs($existing, AuthGuard::TENANT->value);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'email' => 'queries-existing@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $component = Livewire::test(AcceptInvitation::class, ['invitation' => Invitation::query()->findOrFail($invitation->id)]);

    $counts = acceptInvitationQueryCounts(fn () => $component->call('accept'));

    expect($counts)->toBe(['tenant' => 1, 'role' => 1, 'user' => 1]);
});
