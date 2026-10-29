<?php

use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Admin\ChangeAdminUserRole;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function statusActor(AdminRoles $role): Admin
{
    foreach (AdminRoles::cases() as $case) {
        Role::findOrCreate($case->value, AuthGuard::ADMIN->value);
    }

    $admin = Admin::factory()->create();
    $admin->assignRole($role);

    foreach ([AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

function statusTarget(AdminRoles $role = AdminRoles::SUPPORT): Admin
{
    $admin = Admin::factory()->create();
    $admin->assignRole($role);

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
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

it('shows only the status action that fits the status', function () {
    statusActor(AdminRoles::ADMIN);
    $active = statusTarget();
    $inactive = statusTarget();
    $inactive->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $pending = statusTarget();
    $pending->forceFill(['status' => AdminStatus::PENDING])->save();

    Livewire::test(ListAdminUsers::class)
        ->assertActionVisible(TestAction::make('deactivate')->table($active))
        ->assertActionHidden(TestAction::make('activate')->table($active))
        ->assertActionVisible(TestAction::make('activate')->table($inactive))
        ->assertActionHidden(TestAction::make('deactivate')->table($inactive))
        ->assertActionHidden(TestAction::make('deactivate')->table($pending))
        ->assertActionHidden(TestAction::make('sendPasswordResetLink')->table($pending));
});

it('refuses to deactivate yourself or the last active super admin', function () {
    $actor = statusActor(AdminRoles::SUPER_ADMIN);
    $other = statusTarget(AdminRoles::SUPER_ADMIN);
    $other->forceFill(['status' => AdminStatus::INACTIVE])->save();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($actor));

    $other->forceFill(['status' => AdminStatus::ACTIVE])->save();
    $actorSupport = statusActor(AdminRoles::ADMIN);
    $actorSupport->givePermissionTo(AdminPermissions::ADMIN_USER_UPDATE);
    $actor->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $this->actingAs($actorSupport, AuthGuard::ADMIN->value);

    expect($actorSupport->can('deactivate', $other))->toBeFalse();
});

it('keeps an admin from deactivating a super admin', function () {
    $actor = statusActor(AdminRoles::ADMIN);
    $super = statusTarget(AdminRoles::SUPER_ADMIN);
    statusTarget(AdminRoles::SUPER_ADMIN);

    expect($actor->can('deactivate', $super))->toBeFalse()
        ->and($actor->can('changeRole', $super))->toBeFalse()
        ->and($actor->can('sendPasswordResetLink', $super))->toBeFalse();
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

it('refuses to change your own role', function () {
    $actor = statusActor(AdminRoles::ADMIN);

    expect($actor->can('changeRole', $actor))->toBeFalse();
});

it('hides the status actions that do not fit even for a super admin', function () {
    statusActor(AdminRoles::SUPER_ADMIN);
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
});

it('applies a role change on the next request', function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    $actor = Admin::factory()->create();
    $actor->assignRole(AdminRoles::SUPER_ADMIN);
    $target = statusTarget();

    expect($target->can(AdminPermissions::ADMIN_USER_UPDATE))->toBeFalse();

    app(ChangeAdminUserRole::class)->handle($target, AdminRoles::ADMIN->value, $actor);

    expect(Admin::find($target->getKey())->can(AdminPermissions::ADMIN_USER_UPDATE))->toBeTrue()
        ->and(Admin::find($target->getKey())->can(AdminPermissions::TENANT_VIEW_ANY))->toBeTrue();
});
