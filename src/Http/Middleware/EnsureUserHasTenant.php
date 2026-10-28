<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::auth()->check()) {

            $user = Auth::user();

            if (! $user->relationLoaded('tenants')) {
                $user->load('tenants');
            }

            if ($user->tenants->isEmpty()) {
                // Log out from current guard
                Auth::guard(Filament::getAuthGuard())->logout();

                // Invalidate session & regenerate token
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }
}
