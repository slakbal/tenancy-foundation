<?php

use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Listeners\ActivateAdminOnPasswordReset;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

it('activates a pending admin and verifies the email when the password is reset', function () {
    $admin = Admin::factory()->pending()->unverified()->create();

    event(new PasswordReset($admin));

    $admin->refresh();

    expect($admin->status)->toBe(AdminStatus::ACTIVE)
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('leaves an inactive admin inactive after a password reset', function () {
    $admin = Admin::factory()->inactive()->create();

    event(new PasswordReset($admin));

    expect($admin->fresh()->status)->toBe(AdminStatus::INACTIVE);
});

it('ignores a password reset of a tenant user', function () {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create();

    (new ActivateAdminOnPasswordReset)->handle(new PasswordReset($user));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('activates a pending admin through the reset password page', function () {
    $admin = Admin::factory()->pending()->create();
    $token = Password::broker('admins')->createToken($admin);

    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

    Livewire\Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
        ->fillForm(['password' => 'a-new-password-1', 'passwordConfirmation' => 'a-new-password-1'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->status)->toBe(AdminStatus::ACTIVE);
});
