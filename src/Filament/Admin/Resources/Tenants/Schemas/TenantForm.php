<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make(static::mainComponents())->columnSpan(['lg' => 2]),
                Group::make(static::sideComponents())->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function mainComponents(): array
    {
        return [
            static::detailsSection(),
            static::accessSection(),
            static::billingSection(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function sideComponents(): array
    {
        return [
            TenantInfolist::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('Tenant details'))
            ->description(__('The name and web address of the tenant.'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->schema(static::detailsFields())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function detailsFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Tenant name'))
                ->required()
                ->maxLength(60),
            TenantDetailsSchema::address(ignoreRecord: true)
                ->helperText(__('Changing the address changes where the tenant signs in.')),
        ];
    }

    public static function billingSection(): Section
    {
        return Section::make(__('Billing information'))
            ->description(__('Where billing notifications, invoices and receipts are sent.'))
            ->icon(Heroicon::OutlinedCreditCard)
            ->schema(static::billingFields())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function billingFields(): array
    {
        return [
            TextInput::make('billing_email')
                ->label(__('Billing email'))
                ->email()
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    public static function accessSection(): Section
    {
        return Section::make(__('Access'))
            ->description(__('Who may sign in to the tenant. The access rules below allow or block single domains and email addresses.'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema(static::accessFields());
    }

    /**
     * @return array<int, Component>
     */
    protected static function accessFields(): array
    {
        return [
            Select::make('access_policy')
                ->label(__('Access policy'))
                ->options(AccessPolicy::class)
                ->required()
                ->helperText(__('Open: every email address may sign in, except blocked domains and addresses. Allowed domains only: only allowed domains and addresses may sign in. A rule for an email address wins over the rule for its domain.')),
        ];
    }
}
