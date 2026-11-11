<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantUserAbilities;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ChangeMemberStatusAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\LeaveTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\MakeTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ManageTenantRolesAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveFromTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RestoreMemberAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\VerifiedColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\WrappedBadgeColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Filters\RoleFilter;

class UsersTable extends BaseUsersTable
{
    protected static function modifyQuery(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withSoleOwnerFlag()->with([
            'roles',
            'tenants',
            'ownedTenants',
            'memberTenants',
        ]));
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        $columns = collect(parent::columns())
            ->reject(fn (Column $column): bool => in_array($column->getName(), ['created_at', 'last_login_at'], true))
            ->values()
            ->all();

        array_splice($columns, 2, 0, [
            VerifiedColumn::make(),
            TextColumn::make('membership_status')
                ->label(__('Status'))
                ->badge()
                ->state(fn (Model $record): ?MemberStatus => $record->memberStatus()),
            RoleBadgeColumn::make(),
            WrappedBadgeColumn::make('ownedTenants.name')->label(__('Owner of')),
            WrappedBadgeColumn::make('memberTenants.name')->label(__('Member of')),
            TextColumn::make('joined_at')
                ->label(__('Joined'))
                ->state(fn (Model $record): mixed => $record->tenants->firstWhere('id', Filament::getTenant()->getKey())?->pivot->created_at)
                ->dateTime()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    static::membershipQuery()->select('created_at')->whereColumn('tenant_user.user_id', $query->qualifyColumn('id'))->limit(1),
                    $direction,
                )),
            TextColumn::make('last_login_at')
                ->label(__('Last login'))
                ->dateTime()
                ->placeholder('-')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ]);

        return $columns;
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected static function filters(): array
    {
        return [
            RoleFilter::make(),
            SelectFilter::make('membership_status')
                ->label(__('Status'))
                ->options(MemberStatus::class)
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereIn($query->qualifyColumn('id'), static::membershipQuery()->select('user_id')->where('status', $data['value']))
                    : $query),
            TernaryFilter::make('is_owner')
                ->label(__('Owner'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereIn($query->qualifyColumn('id'), static::membershipQuery()->select('user_id')->where('is_owner', true)),
                    false: fn (Builder $query): Builder => $query->whereIn($query->qualifyColumn('id'), static::membershipQuery()->select('user_id')->where('is_owner', false)),
                ),
            ...parent::filters(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make()->hidden(fn (Model $record): bool => $record->trashed()),
            ManageTenantRolesAction::make(tenant: Filament::getTenant(), name: 'changeRole', visible: fn (Model $record): bool => static::canChangeRole($record)),
            ChangeMemberStatusAction::deactivate(),
            ChangeMemberStatusAction::activate(),
            MakeTenantOwnerAction::make(),
            RemoveTenantOwnerAction::make(),
            RestoreMemberAction::make(),
            RemoveFromTenantAction::make(),
            LeaveTenantAction::make(),
        ];
    }

    /**
     * No force delete in the tenant panel, and a deleted user goes through the same action as the row, so the
     * removal event is dispatched. A user who belongs to other tenants is refused by the policy guards.
     *
     * @return array<int, Action|BulkAction>
     */
    protected static function bulkActions(): array
    {
        return [
            DeleteBulkAction::make()
                ->authorizeIndividualRecords('delete')
                ->using(fn (Collection $records) => static::removeMembers($records)),
        ];
    }

    protected static function removeMembers(Collection $records): void
    {
        $tenant = Filament::getTenant();
        $actor = Filament::auth()->user();

        $records
            ->reject(fn (Model $record): bool => $record->isOwner($tenant) || $actor->is($record))
            ->each(fn (Model $record) => app(RemoveTenantMember::class)->handle($tenant, $record, $actor));
    }

    protected static function canChangeRole(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && ! $authenticated->is($record)
            && ! $record->isOwner($tenant)
            && $authenticated->can(TenantUserAbilities::ChangeRole, $record);
    }

    protected static function membershipQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('tenant_user')->where('tenant_id', Filament::getTenant()->getKey());
    }
}
