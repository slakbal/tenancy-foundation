<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Exceptions\InvalidTenancyConfiguration;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

test('the admin panel keeps the admin path and answers on any host by default', function () {
    expect(FilamentPanel::ADMIN->getPath())->toBe('admin')
        ->and(FilamentPanel::ADMIN->getDomain())->toBeNull()
        ->and(Filament::getPanel('admin')->getPath())->toBe('admin')
        ->and(Filament::getPanel('admin')->getDomains())->toBe([]);

    $this->get('http://tenant.test/admin/login')->assertOk();
})->group('subdomain');

test('the admin path and domain come from config', function () {
    config(['tenancy-foundation.admin' => ['domain' => 'backoffice.example.com', 'path' => '']]);

    expect(FilamentPanel::ADMIN->getPath())->toBe('')
        ->and(FilamentPanel::ADMIN->getDomain())->toBe('backoffice.example.com')
        ->and(FilamentPanel::TENANT->getDomain())->toBeNull();
});

test('in domain mode the admin host falls back to the app domain', function (?string $appDomain, ?string $expected) {
    config(['tenancy-foundation.resolution' => 'domain', 'tenancy-foundation.domain' => $appDomain]);

    expect(FilamentPanel::ADMIN->getDomain())->toBe($expected);
})->with([
    'app domain set' => ['tenant.test', 'tenant.test'],
    'app domain empty' => ['', null],
]);

test('the app domain and the admin host are reserved', function () {
    config(['tenancy-foundation.admin.domain' => 'backoffice.tenant.test']);

    expect(TenantResolution::reservedHosts())->toBe(['tenant.test', 'backoffice.tenant.test'])
        ->and(TenantResolution::reservedSubdomains())->toContain('www', 'backoffice');

    config(['tenancy-foundation.admin.domain' => 'backoffice.other.org']);

    expect(TenantResolution::reservedSubdomains())->not->toContain('backoffice');
});

test('domain mode refuses to boot the admin panel without a host', function () {
    config(['tenancy-foundation.resolution' => 'domain', 'tenancy-foundation.domain' => '', 'tenancy-foundation.admin.domain' => null]);

    expect(fn () => AdminPlugin::make()->register(Panel::make()))->toThrow(InvalidTenancyConfiguration::class, 'set ADMIN_DOMAIN or APP_DOMAIN');
});

test('subdomain mode still boots the admin panel on any host without a host', function () {
    config(['tenancy-foundation.resolution' => 'subdomain', 'tenancy-foundation.domain' => '', 'tenancy-foundation.admin.domain' => null]);

    expect(AdminPlugin::make()->register($panel = Panel::make()))->toBeNull()
        ->and($panel->getDomains())->toBe([]);
});
