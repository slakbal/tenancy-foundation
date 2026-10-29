<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns(static::columns())
            ->recordActions(static::recordActions())
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
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
        ];
    }
}
