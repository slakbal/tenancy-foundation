<?php

namespace Slakbal\TenancyFoundation\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberRemoved extends Notification implements ShouldQueue
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
            ->subject(__('You were removed from :tenant', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('You no longer have access to :tenant.', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('If you think this is a mistake, contact the owner of the account.'));
    }
}
