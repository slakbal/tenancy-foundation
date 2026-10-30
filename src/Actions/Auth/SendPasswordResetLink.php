<?php

namespace Slakbal\TenancyFoundation\Actions\Auth;

use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

readonly class SendPasswordResetLink
{
    public function handle(Model $account, FilamentPanel $panel): void
    {
        $token = Password::broker($panel->getPasswordBroker())->createToken($account);

        $notification = app(ResetPassword::class, ['token' => $token]);
        $notification->url = Filament::getPanel($panel->getPanelId())->getResetPasswordUrl($token, $account);

        $account->notify($notification);
    }
}
