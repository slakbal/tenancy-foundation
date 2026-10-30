<?php

use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->user = User::factory()->unverified()->create();
    app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], User::factory()->create())
        ->members()->attach($this->user, ['is_owner' => false, 'is_default_tenant' => true]);
});

function signInAdmin(AdminRoles $role): Admin
{
    $admin = Admin::factory()->create();
    $admin->assignRole($role);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

test('support can impersonate and send a reset link but not edit, verify or delete', function () {
    signInAdmin(AdminRoles::SUPPORT);

    Livewire::test(ViewUser::class, ['record' => $this->user->getRouteKey()])
        ->assertActionVisible('impersonate')
        ->assertActionVisible('sendPasswordResetLink')
        ->assertActionHidden('edit')
        ->assertActionHidden('markEmailVerified')
        ->assertActionHidden('delete');
});

test('an admin gets every user header action', function () {
    signInAdmin(AdminRoles::ADMIN);

    Livewire::test(ViewUser::class, ['record' => $this->user->getRouteKey()])
        ->assertActionVisible('impersonate')
        ->assertActionVisible('sendPasswordResetLink')
        ->assertActionVisible('edit')
        ->assertActionVisible('markEmailVerified')
        ->assertActionVisible('delete');
});

test('the reset link goes to the user with a token the tenant broker accepts', function () {
    Notification::fake();
    signInAdmin(AdminRoles::SUPPORT);

    Livewire::test(ViewUser::class, ['record' => $this->user->getRouteKey()])
        ->callAction('sendPasswordResetLink')
        ->assertNotified();

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification): bool {
        parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);

        return str_contains($notification->url, 'password-reset/reset')
            && Password::broker('users')->tokenExists($this->user, $query['token']);
    });
});

test('marking the email verified sets the timestamp and then hides the action', function () {
    signInAdmin(AdminRoles::ADMIN);

    Livewire::test(ViewUser::class, ['record' => $this->user->getRouteKey()])
        ->callAction('markEmailVerified')
        ->assertNotified()
        ->assertActionHidden('markEmailVerified');

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

test('the users table sends the reset link for support but not for a deleted user', function () {
    Notification::fake();
    signInAdmin(AdminRoles::SUPPORT);
    $deleted = User::factory()->create();
    $deleted->delete();

    Livewire::test(ListUsers::class)
        ->callTableAction('sendPasswordResetLink', $this->user)
        ->assertNotified()
        ->filterTable('trashed', true)
        ->assertTableActionHidden('sendPasswordResetLink', $deleted);

    Notification::assertSentTo($this->user, ResetPassword::class);
});

test('the tenant users tab sends the reset link to a member', function () {
    Notification::fake();
    signInAdmin(AdminRoles::SUPPORT);
    $tenant = $this->user->tenants()->first();
    $row = $tenant->members()->find($this->user->getKey());

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => ViewTenant::class])
        ->callTableAction('sendPasswordResetLink', $row)
        ->assertNotified();

    Notification::assertSentTo($this->user, ResetPassword::class);
});

test('an admin is not offered a reset link for their own account', function () {
    $self = signInAdmin(AdminRoles::SUPER_ADMIN);
    $self->forceFill(['status' => AdminStatus::ACTIVE])->save();
    $other = Admin::factory()->create(['status' => AdminStatus::ACTIVE]);

    Livewire::test(ListAdminUsers::class)
        ->assertTableActionHidden('sendPasswordResetLink', $self)
        ->assertTableActionVisible('sendPasswordResetLink', $other);
});
