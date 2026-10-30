<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Filament::auth()->check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user->relationLoaded('tenants')) {
            $user->load('tenants');
        }

        if ($user->tenants->isEmpty()) {
            $this->signOut($request);

            return $next($request);
        }

        if ($user->hasOnlyCancelledTenants()) {
            $this->signOut($request);

            Notification::make()
                ->title(__('This account has been cancelled.'))
                ->body(__('You have been signed out.'))
                ->warning()
                ->persistent()
                ->send();

            return redirect()->to(Filament::getLoginUrl());
        }

        if ($user->hasNoAccessibleTenants()) {
            $this->signOut($request);

            Notification::make()
                ->title(__('Your access has been deactivated.'))
                ->body(__('Contact the owner of your tenant. You have been signed out.'))
                ->warning()
                ->persistent()
                ->send();

            return redirect()->to(Filament::getLoginUrl());
        }

        return $next($request);
    }

    protected function signOut(Request $request): void
    {
        Auth::guard(Filament::getAuthGuard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
