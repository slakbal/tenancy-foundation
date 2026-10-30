<?php

namespace Slakbal\TenancyFoundation\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;

class AccountReactivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Model $tenant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your :tenant account has been reactivated', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('Your account :tenant has been reactivated.', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('Everyone can sign in again and the scheduled deletion has been cancelled.'))
            ->action(__('Open your tenant'), TenantDetailsSchema::url($this->tenant->subdomain));
    }
}
