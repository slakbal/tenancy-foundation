<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\MarkUserEmailVerifiedAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\SendUserPasswordResetLinkAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ImpersonateUserAction::make(),
            SendUserPasswordResetLinkAction::make(),
            MarkUserEmailVerifiedAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
