<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsNotCancelled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Filament::getTenant()?->isCancelled()) {
            return $next($request);
        }

        $fallback = Filament::auth()->user()?->getDefaultTenant(Filament::getCurrentOrDefaultPanel());

        abort_unless($fallback, Response::HTTP_FORBIDDEN, __('This account has been cancelled.'));

        return redirect()->to(Filament::getUrl($fallback));
    }
}
