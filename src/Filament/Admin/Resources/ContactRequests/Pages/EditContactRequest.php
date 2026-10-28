<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\ChangeContactRequestStatus;
use Slakbal\TenancyFoundation\Actions\RespondToContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('dismiss', ContactStatus::DISMISSED, __('Dismiss'), Heroicon::OutlinedXCircle, 'gray', 'dismiss'),
            $this->statusAction('reopen', ContactStatus::NEW, __('Reopen'), Heroicon::OutlinedArrowUturnLeft, 'warning', 'update'),
            DeleteAction::make()->successRedirectUrl(fn (): string => ContactRequestResource::getUrl()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(__('Respond'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->authorize('respond')
                ->visible(fn (): bool => $this->getRecord()->canBeRespondedTo()),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $record = $this->getRecord();
        $admin = Filament::auth()->user();

        abort_unless($admin->can('respond', $record), 403);

        $data = $this->form->getState();

        app(RespondToContactRequest::class)->handle($record, $data['response'], $admin->name);

        Notification::make()->title(__('Response sent.'))->success()->send();

        $this->redirect(ContactRequestResource::getUrl());
    }

    protected function statusAction(string $name, ContactStatus $status, string $label, Heroicon $icon, string $color, string $ability): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorize($ability)
            ->visible(fn (Model $record): bool => $status === ContactStatus::DISMISSED ? $record->canBeDismissed() : $record->status !== $status)
            ->action(function (Model $record) use ($status): void {
                app(ChangeContactRequestStatus::class)->handle($record, $status);

                $this->refreshFormData(['status']);

                Notification::make()->title(__('Status changed to :status.', ['status' => $status->getLabel()]))->success()->send();
            });
    }
}
