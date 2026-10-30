<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Slakbal\TenancyFoundation\Actions\Invitation\CreateInvitation;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\InvitationIssued;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\InvitationCreated;

beforeEach(function () {
    NotificationFacade::fake();
});

function createInvitationTestRole(): Role
{
    return Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
}

test('it creates an invitation scoped to the tenant with a lower-cased email and the configured expiry', function () {
    config(['tenancy-foundation.invitations.expiry_days' => 3]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $role = createInvitationTestRole();

    $invitation = (new CreateInvitation)->handle($tenant, $user, 'InViTee@Example.com', $role);

    expect($invitation->tenant_id)->toBe($tenant->id);
    expect($invitation->email)->toBe('invitee@example.com');
    expect($invitation->invited_by)->toBe($user->id);
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();
});

test('inviting the same pending email twice updates the existing invitation instead of duplicating it', function () {
    $tenant = Tenant::factory()->create();
    $firstInviter = User::factory()->create();
    $secondInviter = User::factory()->create();
    $role = createInvitationTestRole();

    $first = (new CreateInvitation)->handle($tenant, $firstInviter, 'invitee@example.com', $role);
    $second = (new CreateInvitation)->handle($tenant, $secondInviter, 'invitee@example.com', $role);

    expect($first->id)->toBe($second->id);
    expect(Invitation::where('tenant_id', $tenant->id)->where('email', 'invitee@example.com')->count())->toBe(1);
    expect($second->invited_by)->toBe($secondInviter->id);
});

test('the same email can be invited again for a different tenant without conflict', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create();
    $role = createInvitationTestRole();

    $invitationA = (new CreateInvitation)->handle($tenantA, $user, 'invitee@example.com', $role);
    $invitationB = (new CreateInvitation)->handle($tenantB, $user, 'invitee@example.com', $role);

    expect($invitationA->id)->not->toBe($invitationB->id);
    expect(Invitation::where('email', 'invitee@example.com')->count())->toBe(2);
});

test('creating an invitation dispatches the issued event and sends no mail itself', function () {
    Event::fake([InvitationIssued::class]);
    $role = createInvitationTestRole();

    $invitation = (new CreateInvitation)->handle(Tenant::factory()->create(), User::factory()->create(), 'invitee@example.com', $role);

    Event::assertDispatchedTimes(InvitationIssued::class, 1);
    Event::assertDispatched(InvitationIssued::class, fn ($event): bool => $event->invitation->is($invitation));
    NotificationFacade::assertNothingSent();
});

test('the listener emails the invitee when the event fires', function () {
    $role = createInvitationTestRole();

    $invitation = (new CreateInvitation)->handle(Tenant::factory()->create(), User::factory()->create(), 'invitee@example.com', $role);

    NotificationFacade::assertSentOnDemand(InvitationCreated::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'invitee@example.com' && $notification->invitation->is($invitation));
    NotificationFacade::assertSentOnDemandTimes(InvitationCreated::class, 1);
});
