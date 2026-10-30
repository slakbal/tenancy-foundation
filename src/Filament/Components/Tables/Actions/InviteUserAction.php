<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Invitation\CreateInvitation;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Schemas\InvitationForm;

class InviteUserAction
{
    public static function make(?Model $tenant = null): Action
    {
        return Action::make('create_invite')
            ->label(__('Invite user'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn (): bool => config('tenancy-foundation.features.invitations') && static::allowed($tenant))
            ->modalHeading(__('Invite user'))
            ->modalSubmitActionLabel(__('Send invitation'))
            ->schema(InvitationForm::schema())
            ->action(function (array $data, CreateInvitation $createInvitation) use ($tenant): void {
                $role = config('tenancy-foundation.models.role')::query()->findOrFail($data['role_id']);

                try {
                    $invitation = $createInvitation->handle($tenant ?? Filament::getTenant(), $tenant ? null : Filament::auth()->user(), $data['email'], $role);
                } catch (InvalidInvitation $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(__('Invitation sent to :email.', ['email' => $invitation->email]))
                    ->success()
                    ->send();
            });
    }

    protected static function allowed(?Model $tenant): bool
    {
        return Gate::allows('create', config('tenancy-foundation.models.'.($tenant ? 'user' : 'invitation')));
    }
}
