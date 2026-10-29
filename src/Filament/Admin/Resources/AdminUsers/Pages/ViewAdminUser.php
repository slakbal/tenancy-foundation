<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ResendAdminInvitationAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\AdminUserResource;

class ViewAdminUser extends ViewRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->button()->hiddenLabel(false)->tooltip(null),
            ResendAdminInvitationAction::make(),
        ];
    }
}
