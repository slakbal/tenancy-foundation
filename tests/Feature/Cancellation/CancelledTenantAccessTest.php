<?php

use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Http\Middleware\EnsureTenantIsNotCancelled;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

function userWithTenants(array $cancelled, ?array $default = null): User
{
    $user = User::factory()->create();

    foreach ($cancelled as $subdomain => $isCancelled) {
        $tenant = Tenant::factory()->create(['subdomain' => $subdomain, 'cancelled_at' => $isCancelled ? now() : null]);
        $tenant->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => $subdomain === ($default[0] ?? array_key_first($cancelled))]);
    }

    return $user->fresh();
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();
});

test('only a user whose tenants are all cancelled is kept out of the tenant panel', function (array $tenants, bool $onlyCancelled) {
    $user = userWithTenants($tenants);

    expect($user->hasOnlyCancelledTenants())->toBe($onlyCancelled)
        ->and($user->canAccessPanel(Filament::getPanel('app')))->toBe(! $onlyCancelled);
})->with([
    'only tenant cancelled' => [['gone' => true], true],
    'one active tenant left' => [['gone' => true, 'live' => false], false],
    'no tenant at all' => [[], false],
]);

test('cancelled tenants are left out of the tenant switcher', function () {
    $user = userWithTenants(['gone' => true, 'live' => false]);

    expect($user->getTenants(Filament::getPanel('app'))->pluck('subdomain')->all())->toBe(['live']);
});

test('the default tenant is the active default, else another active tenant, else none', function (array $tenants, ?array $default, ?string $expected) {
    $user = userWithTenants($tenants, $default);

    expect($user->getDefaultTenant(Filament::getPanel('app'))?->subdomain)->toBe($expected);
})->with([
    'cancelled default falls back to an active one' => [['gone' => true, 'live' => false], ['gone'], 'live'],
    'everything cancelled' => [['gone' => true, 'gone2' => true], null, null],
    'active default is preferred' => [['first' => false, 'second' => false], ['second'], 'second'],
]);

test('a request to a cancelled tenant is sent to another active tenant of the user', function () {
    $user = userWithTenants(['gone' => true, 'live' => false]);
    $gone = Tenant::query()->where('subdomain', 'gone')->first();

    $this->actingAsTenantUser($user, $gone);

    $this->get($this->tenantUrl($gone))
        ->assertRedirect(Filament::getUrl(Tenant::query()->where('subdomain', 'live')->first()));
});

test('a request to a cancelled tenant signs the user out and sends them to the login page when they have nowhere else to go', function () {
    $user = userWithTenants(['gone' => true]);
    $gone = Tenant::query()->where('subdomain', 'gone')->first();

    $this->actingAsTenantUser($user, $gone);

    $this->get($this->tenantUrl($gone))
        ->assertRedirect(Filament::getLoginUrl())
        ->assertSessionHas('filament.notifications');

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('a user with an active tenant is not signed out when another of their tenants is cancelled', function () {
    $user = userWithTenants(['gone' => true, 'live' => false]);
    $live = Tenant::query()->where('subdomain', 'live')->first();

    $this->actingAsTenantUser($user, $live);

    $this->get($this->tenantUrl($live));

    expect(auth(AuthGuard::TENANT->value)->check())->toBeTrue();
});

test('signing in is refused for a user of a cancelled tenant', function () {
    $user = userWithTenants(['gone' => true]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('signing in still works for a user with an active tenant', function () {
    $user = userWithTenants(['live' => false]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth(AuthGuard::TENANT->value)->check())->toBeTrue();
});

test('the middleware refuses a cancelled tenant outright when the user has no other tenant to go to', function () {
    $user = userWithTenants(['gone' => true]);
    $gone = Tenant::query()->where('subdomain', 'gone')->first();
    $this->actingAsTenantUser($user, $gone);

    $middleware = app(EnsureTenantIsNotCancelled::class);

    expect(fn () => $middleware->handle(Request::create('/'), fn () => response('ok')))
        ->toThrow(HttpException::class, 'This account has been cancelled.');
});
