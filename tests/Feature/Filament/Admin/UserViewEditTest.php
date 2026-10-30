<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

function userViewActor(AdminPermissions ...$permissions): Admin
{
    $admin = Admin::factory()->create();

    foreach ([AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_VIEW, ...$permissions] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);
});

it('shows the user with the tenant count and last login on the view page', function () {
    userViewActor();
    $user = User::factory()->create(['last_login_ip' => '10.1.2.3']);
    Tenant::factory()->count(2)->create()->each(fn (Tenant $tenant) => $tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => false]));

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($user->email)
        ->assertSee('10.1.2.3')
        ->assertSeeInOrder(['Tenants', '2']);
});

it('shows a deleted user as deleted', function () {
    userViewActor();
    $user = User::factory()->create();
    $user->delete();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])->assertSuccessful()->assertSee('Deleted');
});

it('lets an admin with update permission edit the name and email', function () {
    userViewActor(AdminPermissions::USER_UPDATE);
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'email' => 'renamed@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())->name->toBe('Renamed')->email->toBe('renamed@example.com');
});

it('requires the names and a unique email, ignoring the record itself', function () {
    userViewActor(AdminPermissions::USER_UPDATE);
    $user = User::factory()->create();
    $other = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => '', 'email' => $other->email])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['email' => $user->email])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('points to the deleted user when the email belongs to one', function () {
    userViewActor(AdminPermissions::USER_UPDATE);
    $user = User::factory()->create();
    $deleted = User::factory()->create();
    $deleted->delete();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['email' => $deleted->email])
        ->call('save')
        ->assertHasFormErrors(['email' => 'This email belongs to a deleted user. Restore that user instead.']);
});

it('keeps the edit page away from an admin without update permission', function () {
    userViewActor();
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->assertForbidden();
});
