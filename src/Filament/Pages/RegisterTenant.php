<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\CreateTenant;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;
use Throwable;

class RegisterTenant extends BaseRegisterTenant
{
    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getTenantFormComponents());
    }

    /**
     * @return array<int, Component>
     */
    protected function getTenantFormComponents(): array
    {
        return TenantDetailsSchema::components();
    }

    /**
     * Determine if the user can view/access the registration page.
     * When this returns false, Filament automatically hides the "Register team"
     * button from the tenant switcher dropdown and blocks direct route access.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // 1. Load the relationship into memory if not already loaded
        if (! $user->relationLoaded('tenants')) {
            $user->load('tenants');
        }

        // 2. Check the in-memory collection (0 extra database queries)
        return $user->tenants->isEmpty();
    }

    protected function handleRegistration(array $data): Model
    {
        try {
            return app(CreateTenant::class)->handle($data, auth()->user());
        } catch (Throwable $exception) {
            Notification::make()
                ->title(__('Registration failed'))
                ->body(__('An error occurred while creating your account. Please try again.'))
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    public static function getLabel(): string
    {
        return __('Register tenant');
    }
}
