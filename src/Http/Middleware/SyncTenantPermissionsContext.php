<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncTenantPermissionsContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::hasTenancy()) {
            setPermissionsTeamId(Filament::getTenant()?->getKey());

            $user = Filament::auth()->user();

            if ($user instanceof (config('tenancy-foundation.models.user'))) {
                //Switching tenants drops the cached permissions relation so direct permissions do not leak across tenants
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
        }

        return $next($request);
    }
}
