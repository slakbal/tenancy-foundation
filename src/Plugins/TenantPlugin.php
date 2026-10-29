<?php

namespace Slakbal\TenancyFoundation\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Filament\Pages\EditProfile;
use Slakbal\TenancyFoundation\Filament\Pages\EditTenantProfile;
use Slakbal\TenancyFoundation\Filament\Pages\EmailVerificationPrompt;
use Slakbal\TenancyFoundation\Filament\Pages\RegisterTenant;
use Slakbal\TenancyFoundation\Filament\Pages\ResetPassword;
use Slakbal\TenancyFoundation\Filament\Pages\RegisterUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\ContactUs;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Http\Middleware\EnsureTenantIsNotCancelled;
use Slakbal\TenancyFoundation\Http\Middleware\EnsureUserHasTenant;
use Slakbal\TenancyFoundation\Http\Middleware\SyncTenantPermissionsContext;
use Slakbal\TenancyFoundation\Support\PolicyResolver;
use Slakbal\TenancyFoundation\Support\SoleOwnerGuard;

class TenantPlugin implements Plugin
{
    protected string $userResource = UserResource::class;

    protected string $registerUserPage = RegisterUser::class;

    protected string $registerTenantPage = RegisterTenant::class;

    protected string $editTenantProfilePage = EditTenantProfile::class;

    protected string $contactPage = ContactUs::class;

    protected string $profilePage = EditProfile::class;

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

    public function usingContactPage(string $page): static
    {
        $this->contactPage = $page;

        return $this;
    }

    public function usingProfilePage(string $page): static
    {
        $this->profilePage = $page;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->tenant(config('tenancy-foundation.models.tenant'), slugAttribute: 'subdomain')
            ->tenantDomain('{tenant:subdomain}.'.config('tenancy-foundation.domain'))
            ->tenantRegistration($this->registerTenantPage)
            ->tenantProfile($this->editTenantProfilePage)
            ->tenantMiddleware([SyncTenantPermissionsContext::class, EnsureTenantIsNotCancelled::class], isPersistent: true)
            ->registration($this->registerUserPage)
            ->passwordReset(resetAction: ResetPassword::class)
            ->profile($this->profilePage)
            ->authPasswordBroker(AuthGuard::TENANT->getPasswordBroker())
            ->emailVerification(EmailVerificationPrompt::class)
            ->middleware([EnsureUserHasTenant::class], isPersistent: true)
            ->resources([$this->userResource])
            ->pages([$this->contactPage]);
    }

    public function boot(Panel $panel): void
    {
        Gate::before(function ($user, $ability, $arguments = []): Response|bool|null {
            // Always first!! Blocks deleting a user who is the only owner of any tenant. It’s called first in both Gate::before callbacks, so it can deny delete or forceDelete even for super admins and owners, who would otherwise bypass every policy.
            if ($denial = SoleOwnerGuard::denial($ability, $arguments)) {
                return $denial;
            }

            return method_exists($user, 'isOwner') && $user->isOwner() ? true : null;
        });

        Gate::guessPolicyNamesUsing(fn (string $modelClass): string => PolicyResolver::resolve('Tenant', $modelClass));
    }
}
