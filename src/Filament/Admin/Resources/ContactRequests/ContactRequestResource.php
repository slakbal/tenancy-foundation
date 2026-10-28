<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages\EditContactRequest;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages\ListContactRequests;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Schemas\ContactRequestForm;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Tables\ContactRequestsTable;
use UnitEnum;

class ContactRequestResource extends Resource
{
    protected static ?string $slug = 'contact-requests';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function getModel(): string
    {
        return config('tenancy-foundation.models.contact_request');
    }

    public static function getModelLabel(): string
    {
        return __('Contact request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contact requests');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Support');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', ContactStatus::NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return ContactStatus::NEW->getColor();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContactRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
            'edit' => EditContactRequest::route('/{record}/edit'),
        ];
    }
}
