<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Events\Tenant\TenantMemberRemoved;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Listeners\Tenant\SendMemberRemovedNotification;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\Tenant\MemberRemoved;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

function actionTestMember($tenant, TenantRoles $role): User
{
    $user = User::factory()->create();
    $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);
    app(AssignTenantRole::class)->handle($user, $tenant, $role);

    return $user;
}

beforeEach(function () {
    $this->seed(TenantRolePermissionsSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->admin = actionTestMember($this->tenant, TenantRoles::ADMIN);
    $this->member = actionTestMember($this->tenant, TenantRoles::VIEW_ONLY);
    $this->viewer = actionTestMember($this->tenant, TenantRoles::VIEW_ONLY);
    $this->outsider = User::factory()->create();
    app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], $this->outsider);
    setPermissionsTeamId($this->tenant->getKey());
});

function actionTestActAs(User $user, $tenant): void
{
    test()->actingAs($user, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);
}

test('the role of a member is changed from the table, but never for an owner, yourself or without permission', function () {
    actionTestActAs($this->admin, $this->tenant);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('changeRole', $this->member)
        ->assertTableActionHidden('changeRole', $this->owner)
        ->assertTableActionHidden('changeRole', $this->admin)
        ->callTableAction('changeRole', $this->member, ['roles' => [TenantRoles::ADMIN->value]])
        ->assertNotified();

    expect((new UserTenantRoles($this->member->fresh()))->for($this->tenant)->all())->toBe([TenantRoles::ADMIN->value]);

    actionTestActAs($this->member, $this->tenant);
    Livewire::test(ListUsers::class)->assertTableActionVisible('changeRole', $this->admin);

    actionTestActAs($this->viewer, $this->tenant);
    Livewire::test(ListUsers::class)->assertTableActionHidden('changeRole', $this->admin);
});

test('deleting a user of only this tenant soft deletes them, drops the roles and dispatches the removal event with a queued notice', function () {
    Notification::fake();
    Event::fake([TenantMemberRemoved::class]);
    actionTestActAs($this->admin, $this->tenant);

    Livewire::test(ListUsers::class)->callTableAction('removeFromTenant', $this->member);

    Event::assertDispatched(TenantMemberRemoved::class, fn (TenantMemberRemoved $event): bool => $event->accountDeleted && $event->user->is($this->member) && $event->actor->is($this->admin));

    $member = User::withoutGlobalScopes()->find($this->member->getKey());
    expect($member->trashed())->toBeTrue()
        ->and($member->tenants()->count())->toBe(1)
        ->and((new UserTenantRoles($member))->for($this->tenant)->all())->toBe([]);
});

test('the removal listener mails the removed user, can be disabled and stays quiet when someone leaves on their own', function () {
    Notification::fake();
    $event = new TenantMemberRemoved($this->tenant, $this->member, $this->admin, false);
    $listener = new SendMemberRemovedNotification;

    $listener->handle($event);
    Notification::assertSentOnDemand(MemberRemoved::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $this->member->email);

    Notification::fake();
    $listener->handle(new TenantMemberRemoved($this->tenant, $this->member, $this->member, false));
    config(['tenancy-foundation.notifications.member_removed' => null]);
    $listener->handle($event);
    Notification::assertNothingSent();
});

test('leave tenant removes a non owner through the same action and is not offered for others or for owners', function () {
    $other = User::factory()->create();
    $second = app(CreateTenant::class)->handle(['name' => 'Second', 'subdomain' => 'second'], $other);
    $second->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => false]);
    setPermissionsTeamId($this->tenant->getKey());

    actionTestActAs($this->owner, $this->tenant);
    Livewire::test(ListUsers::class)->assertTableActionHidden('leaveTenant', $this->owner);

    actionTestActAs($this->member, $this->tenant);
    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('leaveTenant', $this->admin)
        ->callTableAction('leaveTenant', $this->member)
        ->assertNotified();

    $member = User::withoutGlobalScopes()->find($this->member->getKey());
    expect($member->trashed())->toBeFalse()
        ->and($member->tenants()->pluck('tenants.id')->all())->toBe([$second->getKey()]);
});

test('a user of only this tenant who leaves has their account soft deleted', function () {
    actionTestActAs($this->member, $this->tenant);

    Livewire::test(ListUsers::class)->callTableAction('leaveTenant', $this->member);

    expect(User::withoutGlobalScopes()->find($this->member->getKey())->trashed())->toBeTrue();
});

test('the users list of one tenant never shows the members of another', function () {
    actionTestActAs($this->admin, $this->tenant);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->owner, $this->admin, $this->member])
        ->assertCanNotSeeTableRecords([$this->outsider])
        ->filterTable('is_owner', true)
        ->assertCanSeeTableRecords([$this->owner])
        ->assertCanNotSeeTableRecords([$this->member, $this->outsider]);
});

test('the owner role is never accepted when changing the role of a member', function () {
    actionTestActAs($this->admin, $this->tenant);

    Livewire::test(ListUsers::class)
        ->callTableAction('changeRole', $this->member, ['roles' => [TenantRoles::OWNER->value]])
        ->assertHasTableActionErrors(['roles.0']);

    expect((new UserTenantRoles($this->member->fresh()))->for($this->tenant)->all())->toBe([TenantRoles::VIEW_ONLY->value]);
});

test('restoring a user deleted with the tenant brings them back as an active member with the chosen role', function () {
    actionTestActAs($this->owner, $this->tenant);

    Livewire::test(ListUsers::class)->callTableAction('removeFromTenant', $this->member);

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->callTableAction('restore', $this->member, ['role' => TenantRoles::ADMIN->value])
        ->assertNotified();

    $member = User::find($this->member->getKey());

    expect($member)->not->toBeNull()
        ->and($member->trashed())->toBeFalse()
        ->and($member->tenants()->get()->firstWhere('id', $this->tenant->getKey())->pivot->status)->toBe(MemberStatus::ACTIVE)
        ->and((new UserTenantRoles($member))->for($this->tenant)->all())->toBe([TenantRoles::ADMIN->value]);
});
