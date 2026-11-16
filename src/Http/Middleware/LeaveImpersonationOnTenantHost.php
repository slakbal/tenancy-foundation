<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use STS\FilamentImpersonate\Events\LeaveImpersonation;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpFoundation\Response;

class LeaveImpersonationOnTenantHost
{
    // Domain mode only, in front of filament-impersonate's "leave" route. The admin was never signed in on the tenant host,
    // so instead of restoring the admin there, the tenant session is dropped and the admin goes back to the backoffice.
    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::isImpersonating()) {
            return $next($request);
        }

        $impersonator = Impersonation::getImpersonator();
        $impersonated = Auth::guard(Impersonation::getImpersonatorGuardUsingName())->user();
        $backTo = $request->session()->get('impersonate.back_to') ?? UserResource::getUrl('index', panel: FilamentPanel::ADMIN->getPanelId());

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        if ($impersonator !== null && $impersonated !== null) {
            event(new LeaveImpersonation($impersonator, $impersonated));
        }

        return redirect()->to($backTo);
    }
}
