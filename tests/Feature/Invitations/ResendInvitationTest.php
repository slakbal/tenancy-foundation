<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Invitation\ResendInvitation;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Pages\ListInvitations;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\Invitation\InvitationCreated;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create();
    $this->tenant->members()->attach($this->admin, ['is_owner' => false, 'is_default_tenant' => true]);
    setPermissionsTeamId($this->tenant->getKey());

    foreach ([InvitationPermissions::INVITATION_VIEW_ANY, InvitationPermissions::INVITATION_CREATE] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        $this->admin->givePermissionTo($permission);
    }

    $this->actingAs($this->admin, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('resending issues a fresh link and expiry, also for an expired invitation, and mails the invitee again', function () {
    Notification::fake();
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create(['expires_at' => now()->subDay()]);
    $oldToken = $invitation->token;

    Livewire::test(ListInvitations::class)
        ->assertTableActionVisible('resend', $invitation)
        ->callTableAction('resend', $invitation)
        ->assertNotified();

    $invitation->refresh();

    expect($invitation->token)->not->toBe($oldToken)
        ->and($invitation->expires_at->isFuture())->toBeTrue();
    Notification::assertSentOnDemand(InvitationCreated::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $invitation->email);
});

test('an accepted invitation cannot be resent and the action needs the create permission', function () {
    $accepted = Invitation::factory()->for($this->tenant, 'tenant')->create(['accepted_at' => now()]);
    $pending = Invitation::factory()->for($this->tenant, 'tenant')->create();

    expect(fn () => app(ResendInvitation::class)->handle($accepted))->toThrow(InvalidInvitation::class);

    Livewire::test(ListInvitations::class)->assertTableActionHidden('resend', $accepted);

    $this->admin->revokePermissionTo(InvitationPermissions::INVITATION_CREATE);
    $this->admin->unsetRelation('permissions');

    // viewing the list needs the create permission too, so the page itself is forbidden
    Livewire::test(ListInvitations::class)->assertForbidden();
    expect($pending->fresh()->token)->toBe($pending->token);
});

test('refreshToken rotates the token and resets the expiry from config without announcing anything', function () {
    Event::fake([InvitationIssued::class]);
    config(['tenancy-foundation.invitations.expiry_days' => 3]);
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create(['expires_at' => now()->subDay()]);
    $oldToken = $invitation->token;

    expect($invitation->refreshToken())->toBe($invitation)
        ->and($invitation->fresh()->token)->not->toBe($oldToken)->toHaveLength(64)
        ->and($invitation->fresh()->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();
    Event::assertNotDispatched(InvitationIssued::class);
});

test('resending rotates the token and announces the invitation exactly once', function () {
    Event::fake([InvitationIssued::class]);
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create(['expires_at' => now()->subDay()]);
    $oldToken = $invitation->token;

    app(ResendInvitation::class)->handle($invitation);

    expect($invitation->fresh()->token)->not->toBe($oldToken);
    Event::assertDispatchedTimes(InvitationIssued::class, 1);
});
