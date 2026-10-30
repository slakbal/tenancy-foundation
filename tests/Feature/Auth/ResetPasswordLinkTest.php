<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Filament\Pages\ResetPassword;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

dataset('reset panels', [
    'admin panel' => ['admin', Admin::class, 'admins'],
    'tenant panel' => ['app', User::class, 'users'],
]);

it('shows the form for a valid link', function (string $panel, string $model, string $broker) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create();
    $token = Password::broker($broker)->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->assertNoRedirect()
        ->assertSuccessful();
})->with('reset panels');

it('sends a replaced link to the request page with a message', function (string $panel, string $model, string $broker) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create();
    $old = Password::broker($broker)->createToken($user);
    Password::broker($broker)->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $old])
        ->assertRedirect(Filament::getRequestPasswordResetUrl())
        ->assertNotified(__('This link is invalid or has expired.'));
})->with('reset panels');

it('sends an expired link to the request page with a message', function (string $panel, string $model, string $broker) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create();
    $token = Password::broker($broker)->createToken($user);

    $this->travel((int) config("auth.passwords.{$broker}.expire", 60) + 1)->minutes();

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->assertRedirect(Filament::getRequestPasswordResetUrl())
        ->assertNotified(__('This link is invalid or has expired.'));
})->with('reset panels');

it('sends an unknown email or token to the request page', function (string $panel, string $model, string $broker) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create();

    Livewire::test(ResetPassword::class, ['email' => 'nobody@example.com', 'token' => 'abc'])
        ->assertRedirect(Filament::getRequestPasswordResetUrl());

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => 'wrong'])
        ->assertRedirect(Filament::getRequestPasswordResetUrl());
})->with('reset panels');
