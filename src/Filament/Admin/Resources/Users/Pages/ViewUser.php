<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\MarkUserEmailVerifiedAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\SendUserPasswordResetLinkAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ImpersonateUserAction::make(),
            SendUserPasswordResetLinkAction::make(),
            MarkUserEmailVerifiedAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
