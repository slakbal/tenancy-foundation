<?php

namespace Slakbal\TenancyFoundation\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Slakbal\TenancyFoundation\Actions\Impersonation\IssueImpersonationHandoff;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Http\Middleware\BindSessionToHost;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class RedeemImpersonationHandoff
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $payload = $this->pull($token);

        abort_unless(is_array($payload), Response::HTTP_FORBIDDEN);

        $tenant = config('tenancy-foundation.models.tenant')::query()->whereKey($payload['tenant'])->first();
        $admin = config('tenancy-foundation.models.admin')::query()->whereKey($payload['admin'])->first();
        $user = config('tenancy-foundation.models.user')::query()->whereKey($payload['user'])->first();

        // The link only works on the host it was issued for, while the admin is still active and the user still a member.
        // The impersonation permission was checked when the link was issued: the admin's gate is not booted on a tenant host.
        abort_unless($tenant !== null && TenantResolution::current()->hostFor($tenant) === $request->getHost(), Response::HTTP_FORBIDDEN);
        abort_unless($admin !== null && $admin->canAccessPanel(Filament::getPanel(FilamentPanel::ADMIN->getPanelId())), Response::HTTP_FORBIDDEN);
        abort_unless($user !== null && $user->canAccessTenant($tenant), Response::HTTP_FORBIDDEN);

        $this->startImpersonation($request, $admin, $user, $payload['back_to']);

        return redirect()->to(TenantResolution::current()->urlFor($tenant));
    }

    // Lock and pull together, so two concurrent requests can never both redeem the same link.
    protected function pull(string $token): mixed
    {
        $key = IssueImpersonationHandoff::CACHE_PREFIX.$token;

        return Cache::lock($key.':lock', 10)->get(fn (): mixed => Cache::pull($key));
    }

    // Mirrors filament-impersonate's session keys, so its banner and "leave" route see the impersonation. No Login event fires.
    protected function startImpersonation(Request $request, Authenticatable $admin, Model $user, string $backTo): void
    {
        $guard = FilamentPanel::TENANT->getAuthGuard();
        $session = $request->session();

        // A fresh session: nothing from an earlier visitor on this host survives, and the session id cannot be fixed in advance.
        $session->invalidate();
        $session->regenerateToken();

        $session->put([
            BindSessionToHost::SESSION_KEY => $request->getHost(),
            Auth::guard($guard)->getName() => $user->getAuthIdentifier(),
            ImpersonateManager::SESSION_KEY => $admin->getAuthIdentifier(),
            ImpersonateManager::SESSION_GUARD => FilamentPanel::ADMIN->getAuthGuard(),
            ImpersonateManager::SESSION_GUARD_USING => $guard,
            'impersonate.back_to' => $backTo,
            'impersonate.guard' => $guard,
        ]);

        Auth::forgetGuards();

        event(new EnterImpersonation($admin, $user));
    }
}
