<?php

use Illuminate\Support\Facades\Route;
use Slakbal\TenancyFoundation\Actions\Impersonation\IssueImpersonationHandoff;
use Slakbal\TenancyFoundation\Http\Middleware\LeaveImpersonationOnTenantHost;

pest()->group('subdomain');

test('subdomain mode keeps filament-impersonate as is, without the domain mode handoff', function () {
    expect(Route::has(IssueImpersonationHandoff::routeName()))->toBeFalse()
        ->and(Route::getRoutes()->getByName('filament-impersonate.leave')->gatherMiddleware())->not->toContain(LeaveImpersonationOnTenantHost::class);
});
