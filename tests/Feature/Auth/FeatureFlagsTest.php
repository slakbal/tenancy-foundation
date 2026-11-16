<?php

use Filament\Panel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

function tenantPanel(array $features = []): Panel
{
    config(['tenancy-foundation.features' => [...config('tenancy-foundation.features'), ...$features]]);

    $panel = Panel::make()->id('flags');
    TenantPlugin::make()->register($panel);

    return $panel;
}

it('keeps every auth feature on by default', function (): void {
    $panel = tenantPanel(['tenant_registration' => true]);

    expect($panel->hasRegistration())->toBeTrue()
        ->and($panel->hasPasswordReset())->toBeTrue()
        ->and($panel->hasEmailVerification())->toBeTrue()
        ->and($panel->hasTenantRegistration())->toBeTrue();
});

it('turns off registration', function (): void {
    expect(tenantPanel(['registration' => false])->hasRegistration())->toBeFalse();
});

it('turns off password reset and registration with password auth off', function (): void {
    $panel = tenantPanel(['password_auth' => false]);

    expect($panel->hasPasswordReset())->toBeFalse()
        ->and($panel->hasRegistration())->toBeFalse();
});

it('turns off email verification', function (): void {
    expect(tenantPanel(['email_verification' => false])->hasEmailVerification())->toBeFalse();
});

it('turns off admin password reset with password auth off', function (): void {
    config(['tenancy-foundation.features.password_auth' => false]);

    $panel = Panel::make()->id('admin-flags');
    AdminPlugin::make()->register($panel);

    expect($panel->hasPasswordReset())->toBeFalse();
});

it('follows the resolution for tenant registration by default', function (): void {
    expect(tenantPanel(['tenant_registration' => null])->hasTenantRegistration())
        ->toBe(TenantResolution::current() === TenantResolution::SUBDOMAIN);
});

it('lets tenant registration be forced on or off', function (): void {
    expect(tenantPanel(['tenant_registration' => true])->hasTenantRegistration())->toBeTrue()
        ->and(tenantPanel(['tenant_registration' => false])->hasTenantRegistration())->toBeFalse();
});
