<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Resources\Users;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

abstract class UserResource extends Resource
{
    public static function getModel(): string
    {
        return config('tenancy-foundation.models.user');
    }

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Users');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return null;
        }

        $name = collect(config('tenancy-foundation.user_name_attributes'))
            ->map(fn (string $attribute): mixed => $record->getAttribute($attribute))
            ->filter()
            ->implode(' ');

        return $name !== '' ? $name : $record->getAttribute('email');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
