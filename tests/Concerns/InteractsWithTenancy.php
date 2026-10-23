<?php

namespace Slakbal\TenancyFoundation\Tests\Concerns;

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

trait InteractsWithTenancy
{
    protected function actingAsTenantUser(User $user, Tenant $tenant): static
    {
        if (! $tenant->members()->where('users.id', $user->getKey())->exists()) {
            $tenant->members()->attach($user, [
                'is_owner' => false,
                'is_default_tenant' => true,
            ]);
        }

        setPermissionsTeamId($tenant->getKey());
        $user->unsetRelation('roles');

        $this->actingAs($user, AuthGuard::TENANT->value);

        Filament::setTenant($tenant);

        return $this;
    }

    protected function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        $domain = config('tenancy-foundation.domain');

        return "http://{$tenant->subdomain}.{$domain}/".ltrim($path, '/');
    }
}
