<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\RestoreTenantMember;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

/**
 * Restores a deleted user and re-attaches them to the current tenant with a role, so they are not left roleless.
 */
class RestoreMemberAction
{
    public static function make(): Action
    {
        return Action::make('restore')
            ->label(__('Restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Model $record): bool => $record->trashed() && Filament::auth()->user()?->can('restore', $record) === true)
            ->modalHeading(fn (Model $record): string => __('Restore :name?', ['name' => $record->name]))
            ->modalDescription(__('The user can sign in to this tenant again with the role below, unless they still have one.'))
            ->schema([
                Select::make('role')
                    ->label(__('Role'))
                    ->options(TenantRoles::assignableOptions())
                    ->default(TenantRoles::VIEW_ONLY->value)
                    ->required(),
            ])
            ->action(function (array $data, Model $record): void {
                app(RestoreTenantMember::class)->handle(Filament::getTenant(), $record, TenantRoles::from($data['role']));

                Notification::make()->title(__(':name was restored.', ['name' => $record->name]))->success()->send();
            });
    }
}
