<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Admin\ChangeAdminUserRole;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Schemas\AdminUserForm;

class ChangeAdminUserRoleAction
{
    public static function make(): Action
    {
        return Action::make('changeRole')
            ->label(__('Change role'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (Model $record): bool => ! $record->trashed())
            ->authorize('changeRole')
            ->modalHeading(fn (Model $record): string => __('Change the role of :email', ['email' => $record->email]))
            ->modalWidth('md')
            ->fillForm(fn (Model $record): array => ['role' => $record->roles->first()?->name])
            ->schema([
                Select::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => AdminUserForm::assignableRoles())
                    ->required(),
            ])
            ->action(function (array $data, Model $record): void {
                app(ChangeAdminUserRole::class)->handle($record, $data['role'], Filament::auth()->user());

                Notification::make()->title(__('Role of :email updated.', ['email' => $record->email]))->success()->send();
            });
    }
}
