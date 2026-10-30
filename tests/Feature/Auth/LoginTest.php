<?php

use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

dataset('login panels', [
    'admin panel' => ['admin', Admin::class, AuthGuard::ADMIN],
    'tenant panel' => ['app', User::class, AuthGuard::TENANT],
]);

test('a user can log in to their panel with valid credentials', function (string $panel, string $model, AuthGuard $guard) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create(['email' => 'person@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth($guard->value)->id())->toBe($user->id);
})->with('login panels');

test('login fails with bad credentials', function (string $panel, string $model, AuthGuard $guard, string $email, string $password) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $model::factory()->create(['email' => 'person@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $email)
        ->set('data.password', $password)
        ->call('authenticate');

    expect(auth($guard->value)->check())->toBeFalse();
})->with('login panels')->with([
    'invalid password' => ['person@example.com', 'wrong-password'],
    'unknown email' => ['nobody@example.com', 'password'],
]);
