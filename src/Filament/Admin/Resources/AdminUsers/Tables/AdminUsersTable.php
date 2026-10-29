<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ActivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ChangeAdminUserRoleAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\DeactivateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\ResendAdminInvitationAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions\SendAdminPasswordResetLinkAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::recordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    RestoreBulkAction::make()->authorizeIndividualRecords('restore'),
                    ForceDeleteBulkAction::make()->authorizeIndividualRecords('forceDelete'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        return [
            ...collect(config('tenancy-foundation.user_name_attributes'))
                ->map(fn (string $attribute): Column => TextColumn::make($attribute)
                    ->label(__(Str::of($attribute)->replace('_', ' ')->ucfirst()->toString()))
                    ->searchable()
                    ->sortable())
                ->all(),
            TextColumn::make('email')
                ->label(__('Email'))
                ->searchable()
                ->sortable()
                ->copyable(),
            RoleBadgeColumn::make('roles.name', AdminRoles::class, __('Role')),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected static function filters(): array
    {
        return [
            SelectFilter::make('role')
                ->label(__('Role'))
                ->options(collect(AdminRoles::cases())->mapWithKeys(fn (AdminRoles $role): array => [$role->value => $role->getLabel()])->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $query, string $role): Builder => $query->whereHas('roles', fn (Builder $roles) => $roles->where('roles.name', $role)),
                )),
            SelectFilter::make('status')
                ->label(__('Status'))
                ->options(AdminStatus::class),
            TrashedFilter::make(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [
            ResendAdminInvitationAction::make()->hiddenLabel()->iconButton()->tooltip(__('Resend invitation')),
            ActionGroup::make([
                static::groupItem(ViewAction::make(), __('View'), Heroicon::OutlinedEye),
                static::groupItem(EditAction::make(), __('Edit'), Heroicon::OutlinedPencilSquare),
                SendAdminPasswordResetLinkAction::make(),
                ChangeAdminUserRoleAction::make(),
                ActivateAdminUserAction::make(),
                DeactivateAdminUserAction::make(),
                static::groupItem(DeleteAction::make(), __('Delete'), Heroicon::OutlinedTrash, 'danger'),
                static::groupItem(RestoreAction::make(), __('Restore'), Heroicon::OutlinedArrowUturnLeft, 'success'),
                static::groupItem(ForceDeleteAction::make(), __('Force delete'), Heroicon::OutlinedTrash, 'danger'),
            ]),
        ];
    }

    protected static function groupItem(Action $action, string $label, Heroicon $icon, ?string $color = null): Action
    {
        return $action->label($label)->icon($icon)->color($color)->grouped()->hiddenLabel(false)->tooltip(null);
    }
}
