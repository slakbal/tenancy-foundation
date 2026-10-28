<?php

namespace Slakbal\TenancyFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCancelled extends Notification implements ShouldQueue
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
        $days = $this->tenant->cancelled_at->startOfDay()->diffInDays($this->tenant->scheduled_for_deletion_at->startOfDay());

        return (new MailMessage)
            ->subject(__('Your :tenant account has been cancelled', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('Your account :tenant has been cancelled and is marked for deletion.', ['tenant' => $this->tenant->getFilamentName()]))
            ->line(__('It will be deleted on :date, :days days from the cancellation.', [
                'date' => displayDate($this->tenant->scheduled_for_deletion_at),
                'days' => $days,
            ]))
            ->line(__('All data will be completely deleted and cannot be recovered.'));
    }
}
