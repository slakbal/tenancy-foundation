<?php

use Illuminate\Support\Facades\Auth;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->url = 'http://acme.tenant.test/';
});

test('a signed in user who is deleted is sent to the home page on their next request', function () {
    $sessionKey = Auth::guard(AuthGuard::TENANT->value)->getName();
    $this->owner->delete();

    $this->withSession([$sessionKey => $this->owner->getKey()])
        ->get($this->url)
        ->assertRedirect(config('app.url'));

    expect(session()->has($sessionKey))->toBeFalse();
});

test('a visitor who was never signed in still goes to the login page', function () {
    $this->get($this->url)->assertRedirect(route('filament.app.auth.login'));
});
