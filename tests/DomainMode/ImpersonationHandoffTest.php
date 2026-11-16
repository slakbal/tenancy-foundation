<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Impersonation\IssueImpersonationHandoff;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    setPermissionsTeamId(0);
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole(AdminRoles::SUPPORT);

    $this->user = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Academy', 'domain' => 'academy.example.com'], User::factory()->create());
    $this->tenant->members()->attach($this->user, ['is_owner' => false, 'is_default_tenant' => true]);

    $this->backTo = 'http://backoffice.test/admin/users/'.$this->user->getRouteKey();
});

function handoffUrl(?string $backTo = null): string
{
    return app(IssueImpersonationHandoff::class)->handle(test()->admin, test()->user, test()->tenant, $backTo ?? test()->backTo);
}

test('impersonating from the backoffice opens a signed one-time link on the tenant host and keeps the admin signed in', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    $this->actingAs($this->admin, AuthGuard::ADMIN->value);
    // A real request has generated route URLs on the admin host before; the link must still point at the tenant host.
    UserResource::getUrl('index', panel: 'admin');

    Livewire::test(ViewUser::class, ['record' => $this->user->getRouteKey()])
        ->callAction('impersonate')
        ->assertRedirectContains('http://academy.example.com/impersonation/handoff/')
        ->assertRedirectContains('signature=');

    expect(Impersonation::isImpersonating())->toBeFalse()
        ->and(auth(AuthGuard::ADMIN->value)->id())->toBe($this->admin->getKey())
        ->and(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('the link signs the admin in as the user on the tenant host, as an impersonation', function () {
    $this->get(handoffUrl())->assertRedirect(TenantResolution::current()->urlFor($this->tenant));

    expect(auth(AuthGuard::TENANT->value)->id())->toBe($this->user->getKey())
        ->and(auth(AuthGuard::ADMIN->value)->check())->toBeFalse()
        ->and(Impersonation::isImpersonating())->toBeTrue()
        ->and(Impersonation::getImpersonator()->is($this->admin))->toBeTrue()
        ->and(session('impersonate.back_to'))->toBe($this->backTo);
});

test('the link works once', function () {
    $url = handoffUrl();

    $this->get($url)->assertRedirect();
    auth()->forgetGuards();

    $this->get($url)->assertForbidden();
});

test('the link expires after a minute', function () {
    $url = handoffUrl();

    $this->travel(IssueImpersonationHandoff::TTL_SECONDS + 1)->seconds();

    $this->get($url)->assertForbidden();
    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('the link is rejected on any other host', function (string $host) {
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'domain' => 'other.example.com'], User::factory()->create());

    $this->get(str_replace('academy.example.com', $host, handoffUrl()))->assertStatus($host === $other->domain ? 403 : 404);
    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
})->with(['other.example.com', 'backoffice.test']);

test('a tampered or unknown token is rejected', function () {
    $this->get(str_replace('/handoff/', '/handoff/x', handoffUrl()))->assertForbidden();

    $this->get('http://academy.example.com/impersonation/handoff/'.str_repeat('a', 64))->assertForbidden();
});

test('the link is rejected when the admin was deactivated or deleted in the meantime', function (string $change) {
    $url = handoffUrl();

    match ($change) {
        'deactivated' => $this->admin->forceFill(['status' => AdminStatus::INACTIVE])->save(),
        'deleted' => $this->admin->delete(),
    };

    $this->get($url)->assertForbidden();
    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
})->with(['deactivated', 'deleted']);

test('an admin without the impersonation permission cannot issue a link', function () {
    $this->admin->syncRoles([]);

    handoffUrl();
})->throws(HttpException::class);

test('the link replaces any session the browser already had on the tenant host', function () {
    $this->actingAs(User::factory()->create(), AuthGuard::TENANT->value);
    $this->withSession(['earlier' => 'value']);

    $this->get(handoffUrl());

    expect(auth(AuthGuard::TENANT->value)->id())->toBe($this->user->getKey())
        ->and(session('earlier'))->toBeNull();
});

test('leaving signs out of the tenant host and returns to the backoffice page', function () {
    $this->get(handoffUrl());

    $this->get('http://academy.example.com/filament-impersonate/leave')->assertRedirect($this->backTo);

    expect(Impersonation::isImpersonating())->toBeFalse()
        ->and(auth(AuthGuard::TENANT->value)->check())->toBeFalse()
        ->and(auth(AuthGuard::ADMIN->value)->check())->toBeFalse();
});

test('leaving never redirects off the backoffice host', function (string $backTo) {
    $this->get(handoffUrl($backTo));

    $this->get('http://academy.example.com/filament-impersonate/leave')
        ->assertRedirect(UserResource::getUrl('index', panel: 'admin'));
})->with([
    'another host' => 'https://evil.example.org/admin',
    'a tenant host' => 'http://academy.example.com/',
    'a non-http scheme' => 'javascript://backoffice.test/%0Aalert(1)',
]);
