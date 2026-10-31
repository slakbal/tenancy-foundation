<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ChangeMemberStatusAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\MakeTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveFromTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveTenantOwnerAction;
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
        $columns = parent::columns();

        array_splice($columns, 2, 0, [
            VerifiedColumn::make(),
            TextColumn::make('membership_status')
                ->label(__('Status'))
                ->badge()
                ->state(fn (Model $record): ?MemberStatus => $record->memberStatus()),
            RoleBadgeColumn::make(),
            WrappedBadgeColumn::make('ownedTenants.name')->label(__('Owner of')),
            WrappedBadgeColumn::make('memberTenants.name')->label(__('Member of')),
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
                    ? $query->whereIn($query->qualifyColumn('id'), DB::table('tenant_user')
                        ->select('user_id')
                        ->where('tenant_id', Filament::getTenant()->getKey())
                        ->where('status', $data['value']))
                    : $query),
            ...parent::filters(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            ChangeMemberStatusAction::deactivate(),
            ChangeMemberStatusAction::activate(),
            MakeTenantOwnerAction::make(),
            RemoveTenantOwnerAction::make(),
            RemoveFromTenantAction::make(),
            ...parent::rowActions(),
        ];
    }
}
