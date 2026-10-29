<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ActivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ChangeAdminUserRoleAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\DeactivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ResendAdminInvitationAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\SendAdminPasswordResetLinkAction;
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
            EditAction::make(),
            ResendAdminInvitationAction::make(),
            SendAdminPasswordResetLinkAction::make(),
            ChangeAdminUserRoleAction::make(),
            ActivateAdminUserAction::make(),
            DeactivateAdminUserAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
            ];
    }
}
