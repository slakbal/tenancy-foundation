<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RegisterTenant extends BaseRegisterTenant
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Tenant name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, $set) {
                        // Automatically slugify the subdomain input from the name if empty
                        $set('subdomain', Str::slug($state));
                    })
                    ->autofocus(),

                TextInput::make('subdomain')
                    ->label(__('Subdomain'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(63)
                    ->unique(config('tenancy-foundation.models.tenant'), 'subdomain')
                    ->prefix('https://')
                    ->suffix('.'.config('tenancy-foundation.domain'))
                    ->rules(['not_in:www,admin,app,api,mail,dev,test']), // Reserve core subdomains
            ]);
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
            return DB::transaction(function () use ($data): Model {
                $tenant = config('tenancy-foundation.models.tenant')::create($data);

                $tenant->members()->attach(auth()->id(), [
                    'is_owner' => true,
                    'is_default_tenant' => true,
                ]);

                return $tenant;
            });
        } catch (Throwable $exception) {
            // Show a notification banner to the user
            Notification::make()
                ->title(__('Registration failed'))
                ->body(__('An error occurred while creating your account. Please try again.'))
                ->danger()
                ->send();

            // Halts execution safely, keeps form inputs intact, and enables retry
            throw new Halt;
        }
    }

    public static function getLabel(): string
    {
        return __('Register tenant');
    }
}
