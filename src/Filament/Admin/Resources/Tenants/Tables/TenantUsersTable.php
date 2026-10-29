<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\EmailColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\NameColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;
use Slakbal\TenancyFoundation\Support\TenantUserRoles;

class TenantUsersTable
{
    public static function configure(Table $table, Model $tenant): Table
    {
        $roles = new TenantUserRoles($tenant);

        return static::modifyQuery($table)
            ->defaultSort('name')
            ->columns(static::columns($roles))
            ->filters(static::filters($roles))
            ->recordActions(static::recordActions());
    }

    protected static function modifyQuery(Table $table): Table
    {
        return $table;
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(TenantUserRoles $roles): array
    {
        return [
            NameColumn::make('name')->label(__('Name')),
            EmailColumn::make('email'),
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
            TextColumn::make('last_login_at')
                ->label(__('Last login'))
                ->dateTime()
                ->placeholder('-')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected static function filters(TenantUserRoles $roles): array
    {
        return [
            SelectFilter::make('role')
                ->label(__('Tenant role'))
                ->options(collect(TenantRoles::cases())->mapWithKeys(fn (TenantRoles $role): array => [$role->value => $role->getLabel()])->all())
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereKey($roles->userIdsWithRole($data['value']))
                    : $query),
            TernaryFilter::make('is_owner')
                ->label(__('Owner'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('tenant_user.is_owner', true),
                    false: fn (Builder $query): Builder => $query->where('tenant_user.is_owner', false),
                ),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('editUser')
                    ->label(__('Edit user'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->authorize('update')
                    ->color('primary')
                    ->url(fn (Model $record): string => UserResource::getUrl('edit', ['record' => $record])),
                ImpersonateUserAction::make(),
            ]),
        ];
    }
}
