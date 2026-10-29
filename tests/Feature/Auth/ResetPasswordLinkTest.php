<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Filament\Pages\ResetPassword;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

it('shows the form for a valid admin link', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create();
    $token = Password::broker('admins')->createToken($admin);

    Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
        ->assertNoRedirect()
        ->assertSuccessful();
});

it('sends an expired or replaced admin link to the request page with a message', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create();
    $old = Password::broker('admins')->createToken($admin);
    Password::broker('admins')->createToken($admin);

    Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $old])
        ->assertRedirect(Filament::getRequestPasswordResetUrl())
        ->assertNotified(__('This link is invalid or has expired.'));
});

it('sends an unknown email or token to the request page', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ResetPassword::class, ['email' => 'nobody@example.com', 'token' => 'abc'])
        ->assertRedirect(Filament::getRequestPasswordResetUrl());
});

it('does the same for tenant users on the tenant panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])->assertNoRedirect();

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => 'wrong'])
        ->assertRedirect(Filament::getRequestPasswordResetUrl());
});
