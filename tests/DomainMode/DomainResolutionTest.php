<?php

use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\AcceptInvitation;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\ContactUs;
use Slakbal\TenancyFoundation\Http\Middleware\BindSessionToHost;
use Slakbal\TenancyFoundation\Http\Responses\Contracts\CancelledTenantResponse;
use Slakbal\TenancyFoundation\Http\Responses\RegistrationResponse;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\Invitation\InvitationCreated;
use Slakbal\TenancyFoundation\Notifications\Tenant\WelcomeToTenant;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Academy', 'subdomain' => 'acme', 'domain' => 'academy.example.com'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Training', 'domain' => 'training.other.org'], User::factory()->create());
});

function sessionCookie(string $url): ?Cookie
{
    return collect(test()->get($url)->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('session.cookie'));
}

function actAsTenantAdmin(AdminPermissions ...$permissions): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    test()->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, ...$permissions), AuthGuard::ADMIN->value);
}

test('the tenant panel resolves tenants by their full domain', function () {
    expect(TenantResolution::current())->toBe(TenantResolution::DOMAIN)
        ->and(Filament::getPanel('app')->getTenantDomain())->toBe('{tenant:domain}')
        ->and(Filament::getPanel('app')->getTenantSlugAttribute())->toBe('domain');
});

test('each tenant is resolved from its own unrelated host', function (string $tenant, string $host) {
    $tenant = $this->{$tenant};
    $this->actingAsTenantUser($tenant->owners()->first(), $tenant);

    $this->get("http://{$host}/contact")->assertOk();

    expect(Filament::getTenant()?->is($tenant))->toBeTrue();
})->with([
    'academy.example.com' => ['tenant', 'academy.example.com'],
    'training.other.org' => ['other', 'training.other.org'],
]);

test('an unknown host or a subdomain of the app domain do not resolve a tenant', function (string $host) {
    $this->actingAsTenantUser($this->owner, $this->tenant);

    $this->get("http://{$host}/contact")->assertNotFound();
})->with([
    'unknown host' => 'nope.example.com',
    'subdomain of the app domain' => 'acme.tenant.test',
]);

test('the app domain stays central and is never taken as a tenant host', function () {
    $this->get('http://tenant.test/contact')
        ->assertOk()
        ->assertDontSeeLivewire(ContactUs::class);

    expect(Filament::getTenant())->toBeNull();
});

test('the tenant of the host is looked up once per request', function () {
    Auth::guard(AuthGuard::TENANT->value)->login($this->owner);
    $lookups = 0;
    DB::listen(function (QueryExecuted $query) use (&$lookups): void {
        $lookups += (int) (str_contains($query->sql, 'from "tenants"') && str_contains($query->sql, '"domain" = ?'));
    });

    $this->get('http://academy.example.com/contact')->assertOk();

    expect($lookups)->toBe(1);
});

test('a member of one tenant cannot open another tenant on its host', function () {
    $this->actingAsTenantUser($this->owner, $this->tenant);

    // A browser never carries the session to another host (host-only cookie, BindSessionToHost); the test forces it,
    // and canAccessPanel() refuses non-members of the host tenant, so Filament answers 403.
    $this->get('http://training.other.org/contact')->assertForbidden();
});

test('tenant urls, emails and redirects point at the tenant domain', function () {
    $this->actingAs($this->owner);

    expect(TenantResolution::current()->urlFor($this->tenant))->toBe('http://academy.example.com')
        ->and(ContactUs::getUrl(panel: 'app', tenant: $this->tenant))->toBe('http://academy.example.com/contact')
        ->and(app(RegistrationResponse::class)->toResponse(request())->getTargetUrl())->toBe('http://academy.example.com')
        ->and((new WelcomeToTenant($this->tenant))->toMail($this->owner)->actionUrl)->toBe('http://academy.example.com');
});

test('the welcome notification is sent with the tenant domain', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $tenant = app(CreateTenant::class)->handle(['name' => 'Third', 'domain' => 'third.example.net'], $owner);

    Notification::assertSentTo($owner, WelcomeToTenant::class, fn (WelcomeToTenant $notification): bool => $notification->toMail($owner)->actionUrl === 'http://third.example.net');
    expect($tenant->subdomain)->toBeNull();
});

test('the session cookie is host only even when SESSION_DOMAIN is set', function () {
    expect(config('session.domain'))->toBeNull()
        ->and(sessionCookie('http://academy.example.com/login')?->getDomain())->toBeNull()
        ->and(sessionCookie('http://training.other.org/login')?->getDomain())->toBeNull();
});

test('the admin tenant form edits the domain and not the subdomain', function () {
    actAsTenantAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertFormFieldExists('domain')
        ->assertFormFieldDoesNotExist('subdomain')
        ->assertSchemaStateSet(['domain' => 'academy.example.com'])
        ->fillForm(['domain' => 'learn.example.org'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->tenant->refresh()->domain)->toBe('learn.example.org');
});

test('the admin tenant form validates the domain', function (string $domain, string $rule) {
    actAsTenantAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(['domain' => $domain])
        ->call('save')
        ->assertHasFormErrors(['domain' => $rule]);
})->with([
    'taken' => ['training.other.org', 'unique'],
    'app domain' => ['tenant.test', 'not_in'],
    'admin host' => ['backoffice.test', 'not_in'],
    'scheme' => ['https://academy.example.com', 'regex'],
    'path' => ['academy.example.com/app', 'regex'],
    'upper case' => ['Academy.Example.com', 'regex'],
    'no tld' => ['academy', 'regex'],
    'empty' => ['', 'required'],
]);

test('the admin tenants table shows the domain column', function () {
    actAsTenantAdmin();

    Livewire::test(ListTenants::class)
        ->assertTableColumnExists('domain')
        ->assertTableColumnDoesNotExist('subdomain')
        ->searchTable('training.other')
        ->assertCanSeeTableRecords([$this->other])
        ->assertCanNotSeeTableRecords([$this->tenant]);
});

test('a session cookie replayed on another tenant host is reset to a guest session', function () {
    $other = User::factory()->create();
    $this->other->members()->attach($other, ['is_owner' => false, 'is_default_tenant' => false]);
    $this->tenant->members()->attach($other, ['is_owner' => false, 'is_default_tenant' => true]);
    $guardKey = Auth::guard(AuthGuard::TENANT->value)->getName();

    $this->withSession([$guardKey => $other->getKey(), BindSessionToHost::SESSION_KEY => 'academy.example.com'])
        ->get('http://training.other.org/contact')
        ->assertRedirect();

    expect(Auth::guard(AuthGuard::TENANT->value)->check())->toBeFalse()
        ->and(session()->has($guardKey))->toBeFalse()
        ->and(session(BindSessionToHost::SESSION_KEY))->toBe('training.other.org');
});

test('a session stays signed in on the host it was started on', function () {
    $guardKey = Auth::guard(AuthGuard::TENANT->value)->getName();

    $this->withSession([$guardKey => $this->owner->getKey(), BindSessionToHost::SESSION_KEY => 'academy.example.com'])
        ->get('http://academy.example.com/contact')
        ->assertOk();

    expect(Auth::guard(AuthGuard::TENANT->value)->id())->toBe($this->owner->getKey());
});

test('the tenant panel, login included, answers only on the host of an existing tenant', function (string $host, int $status) {
    $this->other->delete();

    $this->get("http://{$host}/login")->assertStatus($status);
    $this->get("http://{$host}/")->assertStatus($status === 200 ? 302 : 404);
})->with([
    'tenant host' => ['academy.example.com', 200],
    'unknown host' => ['nope.example.com', 404],
    'app domain' => ['tenant.test', 404],
    'admin host' => ['backoffice.test', 404],
    'deleted tenant host' => ['training.other.org', 404],
]);

test('a cancelled tenant host answers like an unknown host, login included', function (string $path) {
    $this->tenant->forceFill(['cancelled_at' => now()])->save();

    $this->get("http://academy.example.com{$path}")->assertNotFound();
})->with(['/', '/login', '/password-reset/request']);

test('only members of the host tenant can sign in on its host', function (string $host, bool $canAccess) {
    app()->instance('request', Request::create("http://{$host}/login"));

    expect($this->owner->canAccessPanel(Filament::getPanel('app')))->toBe($canAccess);
})->with([
    'own tenant host' => ['academy.example.com', true],
    'another tenant host' => ['training.other.org', false],
]);

test('a user with a pending invitation to the host tenant may sign in there to accept it', function (bool $invitationsOn) {
    config(['tenancy-foundation.features.invitations' => $invitationsOn]);
    Invitation::factory()->create(['tenant_id' => $this->other->getKey(), 'email' => $this->owner->email, 'accepted_at' => null, 'expires_at' => now()->addDay()]);
    app()->instance('request', Request::create('http://training.other.org/login'));

    expect($this->owner->canAccessPanel(Filament::getPanel('app')))->toBe($invitationsOn);
})->with(['invitations on' => true, 'invitations off' => false]);

test('signing in on another tenant host fails like wrong credentials', function () {
    $this->owner->forceFill(['password' => 'secret-password'])->save();
    app()->instance('request', Request::create('http://training.other.org/login'));
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(Login::class)
        ->fillForm(['email' => $this->owner->email, 'password' => 'secret-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('an app can answer a cancelled tenant host with its own response, and the session is still dropped', function () {
    app()->bind(CancelledTenantResponse::class, fn () => new class implements CancelledTenantResponse
    {
        public function toResponse(Request $request, Model $tenant): Response
        {
            return redirect()->away('https://example.com/come-back');
        }
    });
    Auth::guard(AuthGuard::TENANT->value)->login($this->owner);
    $this->tenant->forceFill(['cancelled_at' => now()])->save();

    $this->get('http://academy.example.com/login')->assertRedirect('https://example.com/come-back');
    $this->get('http://nope.example.com/login')->assertNotFound();

    auth()->forgetGuards();
    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('a member is signed out on a cancelled tenant host and stays signed out after reactivation', function () {
    // A real session sign-in, so the test sees whether the session itself is dropped.
    Auth::guard(AuthGuard::TENANT->value)->login($this->owner);
    $this->get('http://academy.example.com/contact')->assertOk();

    $this->tenant->forceFill(['cancelled_at' => now()])->save();
    $this->get('http://academy.example.com/contact')->assertNotFound();

    auth()->forgetGuards();
    $this->tenant->forceFill(['cancelled_at' => null])->save();

    $this->get('http://academy.example.com/contact')->assertRedirect('http://academy.example.com/login');
});

test('an invitation link is signed for the tenant host and opens there', function () {
    $invitation = Invitation::factory()->create(['tenant_id' => $this->tenant->getKey(), 'expires_at' => now()->addDays(7)]);

    $url = (new InvitationCreated($invitation))->toMail($this->owner)->actionUrl;

    expect($url)->toStartWith('http://academy.example.com/invitations/accept/');

    $this->get($url)->assertOk()->assertSee('Join Academy');
});

test('an invitation link points at the tenant host even after URLs for another host were generated', function () {
    $invitation = Invitation::factory()->create(['tenant_id' => $this->tenant->getKey(), 'expires_at' => now()->addDays(7)]);

    // A real backoffice or queue request has built route URLs before, which the cloned URL generator used to keep.
    route('filament.admin.auth.login');

    expect(AcceptInvitation::signedUrl($invitation, now()->addHour()))->toStartWith('http://academy.example.com/invitations/accept/');
});

test('an invitation link opens only on its tenant host', function () {
    $invitation = Invitation::factory()->create(['tenant_id' => $this->tenant->getKey(), 'expires_at' => now()->addDays(7)]);
    $url = AcceptInvitation::signedUrl($invitation, now()->addHour());

    $this->get(str_replace('academy.example.com', $this->other->domain, $url))->assertNotFound();
});
