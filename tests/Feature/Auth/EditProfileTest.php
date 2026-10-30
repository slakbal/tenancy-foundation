<?php

use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Notifications\VerifyEmailChange;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Filament\Pages\EditProfile;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

it('registers the profile page on each panel', function (string $panelId) {
    expect(Filament::getPanel($panelId)->hasProfile())->toBeTrue()
        ->and(Filament::getPanel($panelId)->getProfilePage())->toBe(EditProfile::class);
})->with(['admin', 'app']);

it('lets a user update their own name', function (string $panel, string $model, AuthGuard $guard) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create();
    $this->actingAs($user, $guard->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'New Name', 'email' => $user->email])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('New Name');
})->with([
    'admin' => ['admin', Admin::class, AuthGuard::ADMIN],
    'tenant user' => ['app', User::class, AuthGuard::TENANT],
]);

it('requires the name on the profile form', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(Admin::factory()->create(), AuthGuard::ADMIN->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => ''])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});

it('keeps the old email until the link sent to the new address is used', function (string $panel, string $model, AuthGuard $guard) {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel($panel));
    $user = $model::factory()->create(['password' => 'password']);
    $oldEmail = $user->email;
    $this->actingAs($user, $guard->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'new@example.test', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email)->toBe($oldEmail);
    Notification::assertSentOnDemand(VerifyEmailChange::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'new@example.test'
        || array_key_exists('new@example.test', (array) $notifiable->routes['mail']));
    Notification::assertSentTo($user, NoticeOfEmailChangeRequest::class);
})->with([
    'admin' => ['admin', Admin::class, AuthGuard::ADMIN],
    'tenant user' => ['app', User::class, AuthGuard::TENANT],
]);
