<?php

namespace Slakbal\TenancyFoundation\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Pages\EditTenantProfile;
use Slakbal\TenancyFoundation\Filament\Pages\RegisterTenant;
use Slakbal\TenancyFoundation\Filament\Pages\RegisterUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Http\Middleware\EnsureUserHasTenant;
use Slakbal\TenancyFoundation\Http\Middleware\SyncTenantPermissionsContext;
use Slakbal\TenancyFoundation\Support\PolicyResolver;

class TenantPlugin implements Plugin
{
    protected string $userResource = UserResource::class;

    protected string $registerUserPage = RegisterUser::class;

    protected string $registerTenantPage = RegisterTenant::class;

    protected string $editTenantProfilePage = EditTenantProfile::class;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tenancy-foundation-tenant';
    }

    public function usingUserResource(string $resource): static
    {
        $this->userResource = $resource;

        return $this;
    }

    public function usingRegisterUserPage(string $page): static
    {
        $this->registerUserPage = $page;

        return $this;
    }

    public function usingRegisterTenantPage(string $page): static
    {
        $this->registerTenantPage = $page;

        return $this;
    }

    public function usingEditTenantProfilePage(string $page): static
    {
        $this->editTenantProfilePage = $page;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->tenant(config('tenancy-foundation.models.tenant'), slugAttribute: 'subdomain')
            ->tenantDomain('{tenant:subdomain}.'.config('tenancy-foundation.domain'))
            ->tenantRegistration($this->registerTenantPage)
            ->tenantProfile($this->editTenantProfilePage)
            ->tenantMiddleware([SyncTenantPermissionsContext::class], isPersistent: true)
            ->registration($this->registerUserPage)
            ->middleware([EnsureUserHasTenant::class])
            ->resources([$this->userResource]);
    }

    public function boot(Panel $panel): void
    {
        Gate::before(fn ($user, $ability): ?bool => method_exists($user, 'hasRole') && $user->hasRole(TenantRoles::OWNER) ? true : null);

        Gate::guessPolicyNamesUsing(fn (string $modelClass): string => PolicyResolver::resolve('Tenant', $modelClass));
    }
}
