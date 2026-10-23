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

            // 1. Ensure the collection is in memory (0 extra queries if already loaded)
            if (! $user->relationLoaded('tenants')) {
                $user->load('tenants');
            }

            // 2. Check the in-memory collection length instead of querying the database
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
