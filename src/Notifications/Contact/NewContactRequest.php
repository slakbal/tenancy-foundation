<?php

namespace Slakbal\TenancyFoundation\Notifications\Contact;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;

class NewContactRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Model $contactRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('New contact request'))
            ->body(__(':name: :message', ['name' => $this->contactRequest->name, 'message' => Str::limit($this->contactRequest->message, 80)]))
            ->icon(Heroicon::OutlinedEnvelope)
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->button()
                    ->url($this->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    protected function url(): string
    {
        return ContactRequestResource::getUrl('edit', ['record' => $this->contactRequest], panel: FilamentPanel::ADMIN->getPanelId());
    }
}
