<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Invitation\CreateInvitation;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\InvitationResource;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Schemas\InvitationForm;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('create_invite')
                ->label(__('Invite user'))
                ->modal()
                ->schema(InvitationForm::schema())
                ->using(function (array $data, CreateInvitation $createInvitation): ?Model {
                    $role = config('tenancy-foundation.models.role')::query()->findOrFail($data['role_id']);

                    try {
                        $invitation = $createInvitation->handle(Filament::getTenant(), Filament::auth()->user(), $data['email'], $role);
                    } catch (InvalidInvitation $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return null;
                    }

                    Notification::make()
                        ->title(__('Invitation sent to :email', ['email' => $invitation->email]))
                        ->success()
                        ->send();

                    return $invitation;
                }),
        ];
    }
}
