<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Tests\Support\DeveloperSignInLogin;

function enableDeveloperSignIn(bool $local = true, bool $feature = true): void
{
    config(['tenancy-foundation.features.dev_sign_in' => $feature]);

    if ($local) {
        app()->detectEnvironment(fn (): string => 'local');
    }
}

test('the developer sign in is off by default', function () {
    expect(config('tenancy-foundation.features.dev_sign_in'))->toBeFalse();
});

test('the developer sign in is only shown locally with the feature on', function (bool $local, bool $feature, bool $shown) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    enableDeveloperSignIn($local, $feature);

    $component = Livewire::test(DeveloperSignInLogin::class);

    $shown ? $component->assertSee(__('Developer sign in')) : $component->assertDontSee(__('Developer sign in'));
})->with([
    'local with the feature' => [true, true, true],
    'local without the feature' => [true, false, false],
    'not local with the feature' => [false, true, false],
]);

test('the developer sign in is never available in production, even with the feature on', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    enableDeveloperSignIn(local: false);
    app()->detectEnvironment(fn (): string => 'production');
    $admin = Admin::factory()->create();

    Livewire::test(DeveloperSignInLogin::class)
        ->assertDontSee(__('Developer sign in'))
        ->set('devAccount', $admin->email)
        ->call('signInAsDevAccount')
        ->assertNotFound();

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
});

test('the developer sign in logs an admin in on the admin panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    enableDeveloperSignIn();
    $admin = Admin::factory()->create();

    Livewire::test(DeveloperSignInLogin::class)
        ->set('devAccount', $admin->email)
        ->call('signInAsDevAccount')
        ->assertRedirect();

    expect(auth(AuthGuard::ADMIN->value)->id())->toBe($admin->id);
});

test('the developer sign in answers 404 and signs nobody in when it is not available', function (bool $local, bool $feature) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    enableDeveloperSignIn($local, $feature);
    $admin = Admin::factory()->create();

    Livewire::test(DeveloperSignInLogin::class)
        ->set('devAccount', $admin->email)
        ->call('signInAsDevAccount')
        ->assertNotFound();

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
})->with([
    'not local' => [false, true],
    'feature off' => [true, false],
]);

test('the developer sign in refuses an unknown account', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    enableDeveloperSignIn();

    Livewire::test(DeveloperSignInLogin::class)
        ->set('devAccount', 'nobody@example.com')
        ->call('signInAsDevAccount')
        ->assertNoRedirect();

    expect(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
});

test('on the tenant panel the developer sign in refuses a user who is not a member of the resolved tenant', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    enableDeveloperSignIn();
    $user = User::factory()->create();

    Livewire::test(DeveloperSignInLogin::class)
        ->set('devAccount', $user->email)
        ->call('signInAsDevAccount')
        ->assertNoRedirect();

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});
