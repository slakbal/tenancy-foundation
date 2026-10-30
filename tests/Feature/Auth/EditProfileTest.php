<?php

use Filament\Facades\Filament;
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
