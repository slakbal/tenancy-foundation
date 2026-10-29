<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

class ResetPassword extends BaseResetPassword
{
    #[Unexposed]
    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        parent::mount($email, $token);

        if ($this->tokenIsValid($email ?? request()->query('email'))) {
            return;
        }

        Notification::make()
            ->title(__('This link is invalid or has expired.'))
            ->body(__('Request a new link below to set your password.'))
            ->danger()
            ->send();

        $this->redirect(Filament::getRequestPasswordResetUrl());
    }

    protected function tokenIsValid(?string $email): bool
    {
        $user = filled($email) ? Filament::auth()->getProvider()->retrieveByCredentials(['email' => $email]) : null;

        return $user !== null
            && filled($this->token)
            && Password::broker(Filament::getAuthPasswordBroker())->tokenExists($user, $this->token);
    }
}
