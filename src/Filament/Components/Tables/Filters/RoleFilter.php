<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Filters;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Spatie\Permission\PermissionRegistrar;

class RoleFilter
{
    public const NONE = 'none';

    public static function make(): SelectFilter
    {
        return SelectFilter::make('role')
            ->label(__('Tenant role'))
            ->multiple()
            ->options(fn (): array => [self::NONE => __('No role'), ...static::roles()])
            ->query(function (Builder $query, array $data): Builder {
                $values = collect($data['values'] ?? []);

                return $query->where(fn (Builder $query) => $query
                    ->when($values->contains(self::NONE), fn (Builder $query) => $query->orDoesntHave('roles'))
                    ->when($values->reject(self::NONE)->isNotEmpty(), fn (Builder $query) => $query->orWhereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.name', $values->reject(self::NONE)->all()))));
            });
    }

    /**
     * @return array<string, string>
     */
    protected static function roles(): array
    {
        $registrar = app(PermissionRegistrar::class);
        $team = $registrar->teamsKey;

        return $registrar->getRoleClass()::query()
            ->where('guard_name', AuthGuard::TENANT->value)
            ->where(fn ($query) => $query->whereNull($team)->orWhere($team, $registrar->getPermissionsTeamId()))
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => TenantRoles::tryFrom($name)?->getLabel() ?? Str::headline($name)])
            ->all();
    }
}
