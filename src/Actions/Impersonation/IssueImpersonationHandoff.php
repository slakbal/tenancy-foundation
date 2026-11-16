<?php

namespace Slakbal\TenancyFoundation\Actions\Impersonation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Symfony\Component\HttpFoundation\Response;

readonly class IssueImpersonationHandoff
{
    public const TTL_SECONDS = 60;

    public const CACHE_PREFIX = 'tenancy-foundation:impersonation-handoff:';

    /**
     * Domain mode only: host-only cookies keep the backoffice session off the tenant host, so the admin is handed over
     * with a signed, single-use link that expires after TTL_SECONDS. Returns that link on the tenant's own host.
     */
    public function handle(Authenticatable $admin, Model $user, Model $tenant, ?string $backTo): string
    {
        abort_unless(method_exists($admin, 'canImpersonate') && $admin->canImpersonate(), Response::HTTP_FORBIDDEN);
        abort_if(method_exists($user, 'canBeImpersonated') && ! $user->canBeImpersonated(), Response::HTTP_FORBIDDEN);

        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'admin' => $admin->getAuthIdentifier(),
            'user' => $user->getKey(),
            'tenant' => $tenant->getKey(),
            'back_to' => $this->backToAdminHost($backTo),
        ], self::TTL_SECONDS);

        // Signed relative: a cloned URL generator keeps the current host, so the tenant origin is prefixed here and the
        // controller enforces the host against the stored tenant.
        $path = URL::temporarySignedRoute(self::routeName(), now()->addSeconds(self::TTL_SECONDS), ['token' => $token], absolute: false);

        return rtrim(TenantResolution::current()->urlFor($tenant), '/').$path;
    }

    public static function routeName(): string
    {
        return 'filament.'.FilamentPanel::TENANT->getPanelId().'.impersonation.handoff';
    }

    // "Leave" redirects to this URL from the tenant host, so it must never point anywhere but the backoffice.
    protected function backToAdminHost(?string $backTo): string
    {
        $adminHost = FilamentPanel::ADMIN->getDomain();

        if (filled($backTo) && $adminHost !== null && parse_url($backTo, PHP_URL_HOST) === $adminHost && in_array(parse_url($backTo, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $backTo;
        }

        return UserResource::getUrl('index', panel: FilamentPanel::ADMIN->getPanelId());
    }
}
