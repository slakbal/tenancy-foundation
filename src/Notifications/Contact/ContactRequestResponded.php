<?php

namespace Slakbal\TenancyFoundation\Notifications\Contact;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ContactRequestResponded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Model $contactRequest) {}

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
            ->subject(__('Re: your message to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $this->contactRequest->name]))
            ->line(new HtmlString(nl2br(e($this->contactRequest->response))))
            ->line(__('Your original message:'))
            ->line(new HtmlString('<em>'.nl2br(e($this->contactRequest->message)).'</em>'));
    }
}
