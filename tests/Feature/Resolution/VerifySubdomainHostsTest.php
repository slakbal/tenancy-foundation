<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Http\Responses\Contracts\CancelledTenantResponse;
use Slakbal\TenancyFoundation\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

pest()->group('subdomain');

beforeEach(function () {
    Tenant::factory()->create(['subdomain' => 'acme']);
    Tenant::factory()->create(['subdomain' => 'gone', 'cancelled_at' => now()]);
});

test('by default every subdomain shows the tenant login', function () {
    $this->get('http://nope.tenant.test/login')->assertOk();
    $this->get('http://gone.tenant.test/login')->assertOk();
});

test('with verify_subdomain_hosts only an active tenant subdomain and the central host answer', function (string $host, int $status) {
    config(['tenancy-foundation.verify_subdomain_hosts' => true]);

    $this->get("http://{$host}/login")->assertStatus($status);
})->with([
    'central host' => ['tenant.test', 200],
    'tenant subdomain' => ['acme.tenant.test', 200],
    'unknown subdomain' => ['nope.tenant.test', 404],
    'cancelled tenant subdomain' => ['gone.tenant.test', 404],
    'reserved subdomain' => ['www.tenant.test', 404],
    'nested subdomain' => ['a.acme.tenant.test', 404],
    'host outside the domain' => ['acme.example.org', 404],
]);

test('an app can answer a cancelled tenant subdomain with its own response', function () {
    config(['tenancy-foundation.verify_subdomain_hosts' => true]);
    app()->bind(CancelledTenantResponse::class, fn () => new class implements CancelledTenantResponse
    {
        public function toResponse(Request $request, Model $tenant): Response
        {
            return redirect()->away("https://example.com/come-back?account={$tenant->subdomain}");
        }
    });

    $this->get('http://gone.tenant.test/login')->assertRedirect('https://example.com/come-back?account=gone');
    $this->get('http://nope.tenant.test/login')->assertNotFound();
});
