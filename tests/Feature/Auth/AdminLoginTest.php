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

test('admin login fails with bad credentials', function (string $email, string $password) {
    Admin::factory()->create(['email' => 'admin@example.com']);

    Livewire::test(Login::class)
        ->set('data.email', $email)
        ->set('data.password', $password)
        ->call('authenticate');

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
})->with([
    'invalid password' => ['admin@example.com', 'wrong-password'],
    'unknown email' => ['nobody@example.com', 'password'],
]);
