<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

readonly class SendAdminPasswordResetLink
{
    public function handle(Model $admin): void
    {
        $token = Password::broker(AuthGuard::ADMIN->getPasswordBroker())->createToken($admin);

        $notification = app(ResetPassword::class, ['token' => $token]);
        $notification->url = Filament::getPanel(FilamentPanel::ADMIN->getPanelId())->getResetPasswordUrl($token, $admin);

        $admin->notify($notification);
    }
}
