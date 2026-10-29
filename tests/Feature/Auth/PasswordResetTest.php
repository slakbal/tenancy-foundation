<?php

use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

it('enables password reset with the matching broker on each panel', function (string $panelId, AuthGuard $guard) {
    $panel = Filament::getPanel($panelId);

    expect($panel->hasPasswordReset())->toBeTrue()
        ->and($panel->getAuthPasswordBroker())->toBe($guard->getPasswordBroker());
})->with([
    'admin' => ['admin', AuthGuard::ADMIN],
    'tenant' => ['app', AuthGuard::TENANT],
]);

it('sends an admin a reset link and stores the token in the admin table', function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $admin->email])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($admin, ResetPassword::class);
    expect(DB::table('admin_password_reset_tokens')->where('email', $admin->email)->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $admin->email)->exists())->toBeFalse();
});

it('does not send an admin reset link to a tenant user with the same email', function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->create();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request');

    Notification::assertNothingSent();
});
