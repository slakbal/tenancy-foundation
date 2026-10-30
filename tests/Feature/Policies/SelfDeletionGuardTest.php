<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->first = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->first);
    $this->second = User::factory()->create();
    $this->tenant->members()->attach($this->second->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    setPermissionsTeamId($this->tenant->getKey());
    Filament::setTenant($this->tenant, isQuiet: true);
});

test('an owner can never delete or force delete themselves, even with another owner present', function (string $ability) {
    $response = Gate::forUser($this->second)->inspect($ability, $this->second);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toBe(__('You cannot delete your own account.'));
})->with(['delete', 'forceDelete']);

test('an owner can still delete another owner when more than one owner remains', function () {
    expect(Gate::forUser($this->second)->allows('delete', $this->first))->toBeTrue();
});

test('self deletion is only blocked for delete abilities', function () {
    expect(Gate::forUser($this->second)->allows('update', $this->second))->toBeTrue();
});
