<?php

namespace Slakbal\TenancyFoundation\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Support\PolicyResolver;
use Slakbal\TenancyFoundation\Support\SoleOwnerGuard;

class AdminPlugin implements Plugin
{
    protected string $userResource = UserResource::class;

    protected string $tenantResource = TenantResource::class;

    protected string $contactRequestResource = ContactRequestResource::class;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tenancy-foundation-admin';
    }

    public function usingUserResource(string $resource): static
    {
        $this->userResource = $resource;

        return $this;
    }

    public function usingTenantResource(string $resource): static
    {
        $this->tenantResource = $resource;

        return $this;
    }

    public function usingContactRequestResource(string $resource): static
    {
        $this->contactRequestResource = $resource;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->databaseNotifications();
        $panel->passwordReset();
        $panel->authPasswordBroker(AuthGuard::ADMIN->getPasswordBroker());
        $panel->resources([$this->userResource, $this->tenantResource, $this->contactRequestResource]);
    }

    public function boot(Panel $panel): void
    {
        setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

        Gate::before(function ($user, $ability, $arguments = []): Response|bool|null {
            if ($denial = SoleOwnerGuard::denial($ability, $arguments)) {
                return $denial;
            }

            return method_exists($user, 'hasRole') && $user->hasRole(AdminRoles::SUPER_ADMIN) ? true : null;
        });

        Gate::guessPolicyNamesUsing(fn (string $modelClass): string => PolicyResolver::resolve('Admin', $modelClass));
    }
}
