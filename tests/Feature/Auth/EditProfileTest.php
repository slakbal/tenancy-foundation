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

it('lets an admin update their own name', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = Admin::factory()->create();
    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'New Name', 'email' => $admin->email])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->name)->toBe('New Name');
});

it('requires the name on the profile form', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(Admin::factory()->create(), AuthGuard::ADMIN->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => ''])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});

it('lets a tenant user update their own name', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $user = User::factory()->create();
    $this->actingAs($user, AuthGuard::TENANT->value);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Tenant Person', 'email' => $user->email])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Tenant Person');
});
