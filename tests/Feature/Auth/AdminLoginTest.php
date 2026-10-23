<?php

use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Admin;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('admin can log in with valid credentials', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $admin->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeTrue();
    expect(auth(AuthGuard::ADMIN->value)->id())->toBe($admin->id);
});

test('admin login fails with an invalid password', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $admin->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate');

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
});

test('admin login fails for an unknown email', function () {
    Livewire::test(Login::class)
        ->set('data.email', 'nobody@example.com')
        ->set('data.password', 'password')
        ->call('authenticate');

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
});
