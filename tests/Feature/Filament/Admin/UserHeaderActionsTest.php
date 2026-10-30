<?php

use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
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
