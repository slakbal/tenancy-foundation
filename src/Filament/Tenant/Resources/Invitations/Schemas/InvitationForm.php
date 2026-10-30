<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

class InvitationForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('email')
                ->label(__('Email'))
                ->email()
                ->required()
                ->maxLength(255),
            Select::make('role_id')
                ->label(__('Role'))
                ->options(fn (): array => once(fn (): array => static::roleOptions()))
                ->required(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function roleOptions(): array
    {
        return config('tenancy-foundation.models.role')::query()
            ->where('guard_name', AuthGuard::TENANT->value)
            ->whereIn('name', array_keys(TenantRoles::assignableOptions()))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($role): array => [$role->getKey() => TenantRoles::from($role->name)->getLabel()])
            ->all();
    }
}
