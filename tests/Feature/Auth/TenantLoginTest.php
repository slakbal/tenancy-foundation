<?php

use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('tenant user can log in with valid credentials', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth(AuthGuard::TENANT->value)->check())->toBeTrue();
    expect(auth(AuthGuard::TENANT->value)->id())->toBe($user->id);
});

test('tenant login fails with an invalid password', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate');

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('tenant login fails for an unknown email', function () {
    Livewire::test(Login::class)
        ->set('data.email', 'nobody@example.com')
        ->set('data.password', 'password')
        ->call('authenticate');

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});
