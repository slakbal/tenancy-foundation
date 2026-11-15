<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\ContactUs;
use Slakbal\TenancyFoundation\Http\Middleware\BindSessionToHost;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme', 'domain' => 'academy.example.com'], $this->owner);
});

test('subdomain is the default resolution', function () {
    expect(TenantResolution::current())->toBe(TenantResolution::SUBDOMAIN)
        ->and(Filament::getPanel('app')->getTenantDomain())->toBe('{tenant:subdomain}.tenant.test')
        ->and(Filament::getPanel('app')->getTenantSlugAttribute())->toBe('subdomain');
});

test('the tenant is resolved from its subdomain of the app domain', function () {
    $this->actingAsTenantUser($this->owner, $this->tenant);

    $this->get('http://acme.tenant.test/contact')->assertOk();

    expect(Filament::getTenant()?->is($this->tenant))->toBeTrue();
});

test('an unknown subdomain, a nested subdomain or the full domain of a tenant do not resolve a tenant', function (string $url) {
    $this->actingAsTenantUser($this->owner, $this->tenant);

    $this->get($url)->assertNotFound();
})->with([
    'unknown subdomain' => 'http://nope.tenant.test/contact',
    'nested subdomain' => 'http://x.acme.tenant.test/contact',
    'full tenant domain' => 'http://academy.example.com/contact',
]);

test('tenant urls are built from the subdomain', function () {
    expect(TenantResolution::current()->urlFor($this->tenant))->toBe('http://acme.tenant.test')
        ->and(ContactUs::getUrl(panel: 'app', tenant: $this->tenant))->toBe('http://acme.tenant.test/contact');
});

test('the configured session cookie domain is kept so the session is shared with the central domain', function () {
    config(['session.domain' => '.tenant.test']);

    $cookie = collect($this->get('http://tenant.test/login')->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

    expect($cookie?->getDomain())->toBe('.tenant.test');
});

test('the admin tenant form edits the subdomain and not the domain', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE), AuthGuard::ADMIN->value);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertFormFieldExists('subdomain')
        ->assertFormFieldDoesNotExist('domain')
        ->fillForm(['subdomain' => 'www'])
        ->call('save')
        ->assertHasFormErrors(['subdomain' => 'not_in']);

    Livewire::test(ListTenants::class)
        ->assertTableColumnExists('subdomain')
        ->assertTableColumnDoesNotExist('domain');
});

test('the factory fills both address columns so either mode can use it', function () {
    expect(Tenant::factory()->make())
        ->subdomain->not->toBeEmpty()
        ->domain->toEndWith('.example.test');
});

test('the shared session is not bound to one host in subdomain mode', function () {
    $guardKey = Auth::guard(AuthGuard::TENANT->value)->getName();

    $this->withSession([$guardKey => $this->owner->getKey()])
        ->get('http://acme.tenant.test/contact')
        ->assertOk();

    expect(session()->has(BindSessionToHost::SESSION_KEY))->toBeFalse();
});
