<?php

namespace Slakbal\TenancyFoundation\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeToTenant extends Notification implements ShouldQueue
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
            ->subject(__('Welcome to :app', ['app' => config('app.name')]))
            ->greeting(__('Welcome!'))
            ->line(__('Your tenant :tenant has been created and you are its owner.', ['tenant' => $this->tenant->getFilamentName()]))
            ->action(__('Open your tenant'), $this->tenantUrl())
            ->line(__('If you have not verified your email address yet, please do so using the separate verification email.'));
    }

    protected function tenantUrl(): string
    {
        return appDomainScheme().$this->tenant->subdomain.'.'.config('tenancy-foundation.domain');
    }
}
