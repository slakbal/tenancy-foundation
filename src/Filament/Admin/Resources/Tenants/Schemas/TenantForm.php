<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
            TenantDetailsSchema::subdomain(ignoreRecord: true)
                ->helperText(__('Changing the subdomain changes the address the tenant signs in on.')),
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
}
