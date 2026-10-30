<?php

namespace Slakbal\TenancyFoundation\Notifications\Invitation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\AcceptInvitation;

class InvitationCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Model $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name;
        $expiresAt = Carbon::parse($this->invitation->expires_at);

        $acceptUrl = URL::temporarySignedRoute(
            AcceptInvitation::routeName(),
            $expiresAt,
            ['invitation' => $this->invitation->getRouteKey()],
        );

        return (new MailMessage)
            ->subject(__('Join :tenant', ['tenant' => $tenantName]))
            ->line(__('You have been invited to join :tenant.', ['tenant' => $tenantName]))
            ->action(__('Accept invitation'), $acceptUrl)
            ->line(__('This invitation expires on :date.', ['date' => displayDate($expiresAt)]));
    }
}
