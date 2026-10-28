<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;

class TenantInfolist
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
            static::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('Tenant details'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->schema(static::detailsEntries())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function detailsEntries(): array
    {
        return [
            TextEntry::make('name')
                ->label(__('Tenant name')),
            TextEntry::make('subdomain')
                ->label(__('Subdomain'))
                ->url(fn (Model $record): string => TenantDetailsSchema::url($record->subdomain), shouldOpenInNewTab: true)
                ->copyable(),
        ];
    }

    public static function billingSection(): Section
    {
        return Section::make(__('Billing information'))
            ->icon(Heroicon::OutlinedCreditCard)
            ->schema(static::billingEntries())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function billingEntries(): array
    {
        return [
            TextEntry::make('billing_email')
                ->label(__('Billing email'))
                ->placeholder('-')
                ->copyable(),
        ];
    }

    public static function statusSection(): Section
    {
        return Section::make(__('Status'))
            ->icon(Heroicon::OutlinedSignal)
            ->compact()
            ->schema(static::statusEntries());
    }

    /**
     * @return array<int, Component>
     */
    protected static function statusEntries(): array
    {
        return [
            TextEntry::make('status')
                ->label(__('Status'))
                ->inlineLabel()
                ->badge(),
            TextEntry::make('owners.name')
                ->label(__('Owners'))
                ->inlineLabel()
                ->badge()
                ->placeholder('-'),
            TextEntry::make('created_at')
                ->label(__('Created'))
                ->inlineLabel()
                ->dateTime(),
            TextEntry::make('cancelled_at')
                ->label(__('Cancelled'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
            TextEntry::make('scheduled_for_deletion_at')
                ->label(__('Deletion scheduled'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
        ];
    }
}
