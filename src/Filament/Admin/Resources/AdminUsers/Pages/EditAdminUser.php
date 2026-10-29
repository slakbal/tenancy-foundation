<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Admin\UpdateAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ActivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ChangeAdminUserRoleAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\DeactivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ResendAdminInvitationAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\SendAdminPasswordResetLinkAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\AdminUserResource;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ResendAdminInvitationAction::make(),
            SendAdminPasswordResetLinkAction::make(),
            ChangeAdminUserRoleAction::make(),
            ActivateAdminUserAction::make(),
            DeactivateAdminUserAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(), ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateAdminUser::class)->handle($record, $data);
    }
}
