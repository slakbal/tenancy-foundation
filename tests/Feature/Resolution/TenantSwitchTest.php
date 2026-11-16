<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->first = app(CreateTenant::class)->handle(['name' => 'First Academy', ...tenantAddress('first')], User::factory()->create());
    $this->second = app(CreateTenant::class)->handle(['name' => 'Second Academy', ...tenantAddress('second')], User::factory()->create());
    $this->first->members()->attach($this->user, ['is_owner' => false, 'is_default_tenant' => true]);
    $this->second->members()->attach($this->user, ['is_owner' => false, 'is_default_tenant' => false]);
});

test('a member of two tenants sees both in the switcher, each linking to its own host', function () {
    $panel = Filament::getPanel('app');
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    setPermissionsTeamId($this->second->getKey());
    $this->actingAs($this->user, AuthGuard::TENANT->value);

    expect($this->user->getTenants($panel)->pluck('name')->all())->toEqualCanonicalizing(['First Academy', 'Second Academy'])
        ->and(parse_url($panel->getUrl($this->second), PHP_URL_HOST))->toBe(TenantResolution::current()->hostFor($this->second));
});

test('switching tenants opens the other tenant, not the one resolved first', function (bool $verifySubdomainHosts) {
    config(['tenancy-foundation.verify_subdomain_hosts' => $verifySubdomainHosts]);
    Auth::guard(AuthGuard::TENANT->value)->login($this->user);

    $this->get($this->tenantUrl($this->first, '/contact'))->assertOk()->assertSee('First Academy');
    expect(Filament::getTenant()->is($this->first))->toBeTrue();

    if (TenantResolution::current() === TenantResolution::DOMAIN) {
        // Host-only session: the other tenant's host starts as a guest, the user signs in there once.
        Auth::forgetGuards();
        $this->get($this->tenantUrl($this->second, '/contact'))->assertRedirect($this->tenantUrl($this->second, '/login'));
        Auth::guard(AuthGuard::TENANT->value)->login($this->user);
    }

    $this->get($this->tenantUrl($this->second, '/contact'))->assertOk()->assertSee('Second Academy');
    expect(Filament::getTenant()->is($this->second))->toBeTrue();
})->with(['host check off' => false, 'host check on' => true]);

