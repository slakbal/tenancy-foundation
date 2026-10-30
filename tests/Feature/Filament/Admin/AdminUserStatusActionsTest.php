<?php

use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use Slakbal\TenancyFoundation\Models\Admin;

function statusActor(AdminRoles $role): Admin
{
    $admin = adminWithAccess($role, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

function statusTarget(AdminRoles $role = AdminRoles::SUPPORT): Admin
{
    return adminWithAccess($role);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
});

it('deactivates an active admin and clears the remember token', function () {
    statusActor(AdminRoles::ADMIN);
    $target = statusTarget();
    $target->forceFill(['remember_token' => 'token'])->save();

    Livewire::test(ListAdminUsers::class)
        ->callAction(TestAction::make('deactivate')->table($target))
        ->assertNotified();

    expect($target->fresh())->status->toBe(AdminStatus::INACTIVE)->remember_token->toBeNull();
});

it('activates an inactive admin', function () {
    statusActor(AdminRoles::ADMIN);
    $target = statusTarget();
    $target->forceFill(['status' => AdminStatus::INACTIVE])->save();

    Livewire::test(ListAdminUsers::class)
        ->callAction(TestAction::make('activate')->table($target))
        ->assertNotified();

    expect($target->fresh()->status)->toBe(AdminStatus::ACTIVE);
});

it('hides the deactivate action on your own row', function () {
    $actor = statusActor(AdminRoles::SUPER_ADMIN);
    statusTarget(AdminRoles::SUPER_ADMIN);

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($actor));
});

it('hides the deactivate action on the last active super admin', function () {
    $actor = statusActor(AdminRoles::SUPER_ADMIN);
    $actor->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $last = statusTarget(AdminRoles::SUPER_ADMIN);

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($last));
});

it('sends a password reset link to an active admin', function () {
    Notification::fake();
    statusActor(AdminRoles::ADMIN);
    $target = statusTarget();

    Livewire::test(ViewAdminUser::class, ['record' => $target->getRouteKey()])
        ->callAction('sendPasswordResetLink')
        ->assertNotified();

    Notification::assertSentTo($target, ResetPassword::class);
});

it('changes the role of another admin', function () {
    statusActor(AdminRoles::ADMIN);
    $target = statusTarget();

    Livewire::test(ListAdminUsers::class)
        ->callAction(TestAction::make('changeRole')->table($target), ['role' => AdminRoles::ADMIN->value])
        ->assertNotified();

    expect($target->fresh()->hasRole(AdminRoles::ADMIN))->toBeTrue()
        ->and($target->fresh()->hasRole(AdminRoles::SUPPORT))->toBeFalse();
});

it('shows only the status action that fits the status', function (AdminRoles $role) {
    statusActor($role);
    $active = statusTarget();
    $inactive = statusTarget();
    $inactive->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $pending = statusTarget();
    $pending->forceFill(['status' => AdminStatus::PENDING])->save();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('activate')->table($active))
        ->assertActionVisible(TestAction::make('deactivate')->table($active))
        ->assertActionHidden(TestAction::make('deactivate')->table($inactive))
        ->assertActionVisible(TestAction::make('activate')->table($inactive))
        ->assertActionHidden(TestAction::make('deactivate')->table($pending))
        ->assertActionHidden(TestAction::make('sendPasswordResetLink')->table($inactive))
        ->assertActionHidden(TestAction::make('sendPasswordResetLink')->table($pending));
})->with([AdminRoles::ADMIN, AdminRoles::SUPER_ADMIN]);
