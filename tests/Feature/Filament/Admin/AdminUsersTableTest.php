<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\EditAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Role;

function tableActor(AdminRoles $role, AdminPermissions ...$permissions): Admin
{
    $admin = adminWithAccess($role, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, ...$permissions);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

function tableTarget(AdminRoles $role = AdminRoles::SUPPORT): Admin
{
    return adminWithAccess($role);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

it('filters by role', function () {
    tableActor(AdminRoles::ADMIN);
    $support = tableTarget(AdminRoles::SUPPORT);
    $other = tableTarget(AdminRoles::ADMIN);

    Livewire::test(ListAdminUsers::class)
        ->filterTable('role', AdminRoles::SUPPORT->value)
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters by status', function () {
    tableActor(AdminRoles::ADMIN);
    $pending = tableTarget();
    $pending->forceFill(['status' => AdminStatus::PENDING])->save();
    $active = tableTarget();

    Livewire::test(ListAdminUsers::class)
        ->filterTable('status', AdminStatus::PENDING->value)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active]);
});

it('hides deleted admins until the trashed filter asks for them', function () {
    tableActor(AdminRoles::ADMIN);
    $deleted = tableTarget();
    $deleted->delete();

    Livewire::test(ListAdminUsers::class)
        ->assertCanNotSeeTableRecords([$deleted])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$deleted]);
});

it('soft deletes and restores an admin from the table', function () {
    tableActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_DELETE, AdminPermissions::ADMIN_USER_RESTORE);
    $target = tableTarget();

    Livewire::test(ListAdminUsers::class)
        ->callAction(TestAction::make('delete')->table($target))
        ->assertNotified();

    expect($target->fresh()->trashed())->toBeTrue();

    Livewire::test(ListAdminUsers::class)
        ->filterTable('trashed', true)
        ->callAction(TestAction::make('restore')->table($target))
        ->assertNotified();

    expect($target->fresh()->trashed())->toBeFalse();
});

it('offers no delete on the own row nor on the last active super admin', function () {
    $actor = tableActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_DELETE);
    $actor->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $last = tableTarget(AdminRoles::SUPER_ADMIN);

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($actor))
        ->assertActionHidden(TestAction::make('delete')->table($last));
});

it('offers force delete only to a super admin and only on deleted admins', function () {
    $actor = tableActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_DELETE, AdminPermissions::ADMIN_USER_FORCE_DELETE);
    $deleted = tableTarget();
    $deleted->delete();

    Livewire::test(ListAdminUsers::class)
        ->filterTable('trashed', true)
        ->assertActionHidden(TestAction::make('forceDelete')->table($deleted));

    Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $actor->syncRoles([AdminRoles::SUPER_ADMIN]);
    $actor->unsetRelation('roles');
    $live = tableTarget();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('forceDelete')->table($live))
        ->filterTable('trashed', true)
        ->callAction(TestAction::make('forceDelete')->table($deleted));

    expect(Admin::withTrashed()->whereKey($deleted->getKey())->exists())->toBeFalse();
});

it('shows delete and restore in the page headers', function () {
    tableActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_UPDATE, AdminPermissions::ADMIN_USER_DELETE, AdminPermissions::ADMIN_USER_RESTORE);
    $target = tableTarget();

    Livewire::test(ViewAdminUser::class, ['record' => $target->getRouteKey()])
        ->assertActionVisible('delete')
        ->assertActionHidden('restore');

    Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()])
        ->callAction('delete')
        ->assertNotified();

    expect($target->fresh()->trashed())->toBeTrue();

    Livewire::test(ViewAdminUser::class, ['record' => $target->getRouteKey()])
        ->assertActionHidden('delete')
        ->assertActionVisible('restore');
});

it('shows labels for the grouped row actions', function () {
    tableActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_UPDATE, AdminPermissions::ADMIN_USER_DELETE);
    tableTarget();

    $html = Livewire::test(ListAdminUsers::class)->html();

    $missing = collect(['View', 'Edit', 'Delete', 'Change role'])
        ->reject(fn (string $label): bool => (bool) preg_match('/fi-dropdown-list-item-label[^>]*>\s*'.$label.'\s*</', $html))
        ->all();

    expect($missing)->toBe([]);
});
