<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\InviteUserAction;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\InvitationResource;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            InviteUserAction::make(),
        ];
    }
}
