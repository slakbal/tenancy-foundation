<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Admin\ChangeAdminUserRole;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\EditAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Role;

function adminUserActor(AdminRoles $role = AdminRoles::ADMIN, AdminPermissions ...$permissions): Admin
{
    $admin = adminWithAccess($role, ...$permissions);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

function adminUserTarget(AdminRoles $role = AdminRoles::SUPPORT): Admin
{
    return adminWithAccess($role);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

it('lists admins with their roles for an admin who may view them', function () {
    adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY);
    $target = adminUserTarget();

    Livewire::test(ListAdminUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$target])
        ->assertSee(AdminRoles::SUPPORT->getLabel());
});

it('keeps the admin users away from someone without permission', function () {
    adminUserActor();

    Livewire::test(ListAdminUsers::class)->assertForbidden();
});

it('shows the view page with the status', function () {
    adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW);
    $target = adminUserTarget();

    Livewire::test(ViewAdminUser::class, ['record' => $target->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet(['status' => AdminStatus::ACTIVE]);
});

it('updates name and email through the edit page', function () {
    adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE);
    $target = adminUserTarget();

    Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()])
        ->assertFormFieldDoesNotExist('role')
        ->fillForm(['name' => 'Renamed', 'email' => 'renamed@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->name)->toBe('Renamed')
        ->and($target->email)->toBe('renamed@example.com')
        ->and($target->status)->toBe(AdminStatus::ACTIVE)
        ->and($target->hasRole(AdminRoles::SUPPORT))->toBeTrue();
});

it('validates the email and keeps it unique', function () {
    adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE);
    $target = adminUserTarget();
    $other = adminUserTarget();

    Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['email' => 'not-an-email'])
        ->call('save')
        ->assertHasFormErrors(['email' => 'email']);

    Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['email' => $other->email])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);

    Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['name' => 'Same Email Kept', 'email' => $target->email])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('only offers the super admin role to a super admin', function () {
    $actor = adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE);
    $target = adminUserTarget();

    Livewire::test(ListAdminUsers::class)
        ->mountAction(TestAction::make('changeRole')->table($target))
        ->assertFormFieldExists('role', fn ($field): bool => ! array_key_exists(AdminRoles::SUPER_ADMIN->value, $field->getOptions()));

    Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $actor->syncRoles([AdminRoles::SUPER_ADMIN]);
    $actor->unsetRelation('roles');

    Livewire::test(ListAdminUsers::class)
        ->mountAction(TestAction::make('changeRole')->table($target))
        ->assertFormFieldExists('role', fn ($field): bool => array_key_exists(AdminRoles::SUPER_ADMIN->value, $field->getOptions()));
});

it('refuses to let a non super admin assign the super admin role', function () {
    $actor = adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_UPDATE);
    $target = adminUserTarget();

    expect(fn () => app(ChangeAdminUserRole::class)->handle($target, AdminRoles::SUPER_ADMIN->value, $actor))
        ->toThrow(AuthorizationException::class);

    expect($target->fresh()->hasRole(AdminRoles::SUPER_ADMIN))->toBeFalse();
});

it('keeps the own role', function () {
    $actor = adminUserActor(AdminRoles::SUPER_ADMIN);
    adminUserTarget(AdminRoles::SUPER_ADMIN);

    app(ChangeAdminUserRole::class)->handle($actor, AdminRoles::SUPPORT->value, $actor);

    expect($actor->fresh()->hasRole(AdminRoles::SUPER_ADMIN))->toBeTrue()
        ->and($actor->can('changeRole', $actor))->toBeFalse();
});

it('keeps the role of the last active super admin', function () {
    $actor = adminUserActor(AdminRoles::SUPER_ADMIN);
    $actor->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $last = adminUserTarget(AdminRoles::SUPER_ADMIN);

    app(ChangeAdminUserRole::class)->handle($last, AdminRoles::SUPPORT->value, $actor);

    expect($last->fresh()->hasRole(AdminRoles::SUPER_ADMIN))->toBeTrue();
});

it('keeps admins away from editing super admins', function () {
    adminUserActor(AdminRoles::ADMIN, AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_UPDATE);
    $super = adminUserTarget(AdminRoles::SUPER_ADMIN);

    Livewire::test(EditAdminUser::class, ['record' => $super->getRouteKey()])->assertForbidden();
});

it('renders the view and edit pages per role using the seeded permissions', function (AdminRoles $role, bool $canEdit) {
    $this->seed(AdminRolePermissionsSeeder::class);
    $actor = Admin::factory()->create();
    $actor->assignRole($role);
    $this->actingAs($actor, AuthGuard::ADMIN->value);
    $target = adminUserTarget();

    Livewire::test(ViewAdminUser::class, ['record' => $target->getRouteKey()])->assertSuccessful();

    $edit = Livewire::test(EditAdminUser::class, ['record' => $target->getRouteKey()]);
    $canEdit ? $edit->assertSuccessful() : $edit->assertForbidden();
})->with([
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
    'admin' => [AdminRoles::ADMIN, true],
    'support' => [AdminRoles::SUPPORT, false],
]);
