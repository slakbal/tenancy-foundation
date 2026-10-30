<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\ChangeUserTenantRoleAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions\MakeUserTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

class UserTenantsTable
{
    public static function configure(Table $table, Model $user): Table
    {
        $roles = new UserTenantRoles($user);

        return static::modifyQuery($table)
            ->defaultSort('name')
            ->columns(static::columns($roles))
            ->recordActions(static::recordActions($user, $roles));
    }

    protected static function modifyQuery(Table $table): Table
    {
        return $table;
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(UserTenantRoles $roles): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Tenant'))
                ->searchable()
                ->sortable(),
            TextColumn::make('subdomain')
                ->label(__('Subdomain'))
                ->searchable()
                ->sortable(),
            RoleBadgeColumn::make('tenant_roles')
                ->state(fn (Model $record): array => $roles->for($record)->all())
                ->placeholder(__('No role')),
            IconColumn::make('is_owner')
                ->label(__('Owner'))
                ->state(fn (Model $record): bool => (bool) $record->pivot->is_owner)
                ->boolean()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('tenant_user.is_owner', $direction)),
            TextColumn::make('joined_at')
                ->label(__('Joined'))
                ->state(fn (Model $record) => $record->pivot->created_at)
                ->dateTime()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('tenant_user.created_at', $direction)),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge(),
        ];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected static function recordActions(Model $user, UserTenantRoles $roles): array
    {
        return [
            ActionGroup::make([
                Action::make('openTenant')
                    ->label(__('Open tenant'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->authorize('view')
                    ->url(fn (Model $record): string => TenantResource::getUrl('view', ['record' => $record])),
                MakeUserTenantOwnerAction::make($user),
                ChangeUserTenantRoleAction::make($user, $roles),
            ]),
        ];
    }
}
