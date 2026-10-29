<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Admin\InviteAdminUser;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

class ResendAdminInvitationAction
{
    public static function make(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('Resend invitation'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->authorize('update')
            ->visible(fn (Model $record): bool => InviteAdminUser::isEnabled() && $record->status === AdminStatus::PENDING && ! $record->trashed())
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Resend the invitation to :email?', ['email' => $record->email]))
            ->modalDescription(__('A new link to set a password is emailed. Earlier links stop working.'))
            ->action(function (Model $record): void {
                app(InviteAdminUser::class)->handle($record);

                Notification::make()->title(__('Invitation sent to :email.', ['email' => $record->email]))->success()->send();
            });
    }
}
