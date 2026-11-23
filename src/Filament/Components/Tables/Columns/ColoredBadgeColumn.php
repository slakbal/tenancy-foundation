<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\BadgeColor;

class ColoredBadgeColumn
{
    public static function make(string $name, string $defaultColor = BadgeColor::DEFAULT->value): TextColumn
    {
        $column = TextColumn::make($name)->badge();

        if (! str_contains($name, '.')) {
            return $column->color(fn (Model $record): array|string => static::colorOf($record, $defaultColor));
        }

        [$relation, $attribute] = [str($name)->beforeLast('.')->toString(), str($name)->afterLast('.')->toString()];

        return $column->color(fn (string $state, Model $record): array|string => static::colorOf(static::relatedModel($record->{$relation}, $attribute, $state), $defaultColor));
    }

    protected static function relatedModel(mixed $related, string $attribute, string $state): ?Model
    {
        return $related instanceof Model ? $related : $related?->firstWhere($attribute, $state);
    }

    protected static function colorOf(?Model $model, string $defaultColor): array|string
    {
        return $model?->color === null ? $defaultColor : $model->badgeColor();
    }
}
