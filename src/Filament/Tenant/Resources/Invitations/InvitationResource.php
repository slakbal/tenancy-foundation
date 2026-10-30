<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Pages\ListInvitations;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Tables\InvitationsTable;

class InvitationResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'invitations';

    public static function getModel(): string
    {
        return config('tenancy-foundation.models.invitation');
    }

    public static function getModelLabel(): string
    {
        return __('Invitation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Invitations');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Users');
    }

    public static function table(Table $table): Table
    {
        return InvitationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}
