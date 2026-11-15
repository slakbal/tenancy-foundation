<?php

use Illuminate\Support\Facades\URL;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Models\Tenant;

test('tenant urls keep the scheme and port of the generated urls and only swap the host', function () {
    $tenant = Tenant::factory()->create(tenantAddress('acme'));
    URL::forceRootUrl('http://localhost:8000');
    URL::forceScheme('https');

    expect(TenantResolution::current()->urlFor($tenant))->toBe('https://'.TenantResolution::current()->hostFor($tenant).':8000');
});
