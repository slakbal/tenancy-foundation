<?php

namespace Slakbal\TenancyFoundation\Notifications;

use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

class AdminInvited extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $broker = AuthGuard::ADMIN->getPasswordBroker();
        $token = Password::broker($broker)->createToken($notifiable);

        return (new MailMessage)
            ->subject(__('You have been invited to the :app admin panel', ['app' => config('app.name')]))
            ->line(__('An admin account has been created for you on :app.', ['app' => config('app.name')]))
            ->line(__('Choose your password to activate the account.'))
            ->action(__('Set your password'), Filament::getPanel(FilamentPanel::ADMIN->getPanelId())->getResetPasswordUrl($token, $notifiable))
            ->line(__('This link expires in :minutes minutes. Ask an admin to resend the invitation after that.', ['minutes' => (int) config("auth.passwords.{$broker}.expire")]));
    }
}
