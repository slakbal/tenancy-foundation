<?php

use Illuminate\Support\Carbon;

if (! function_exists('upperCaseWordOrNull')) {
    function upperCaseWordOrNull($value = null)
    {
        return filled($value) ? ucwords(mb_strtolower(mb_trim($value))) : null;
    }
}

if (! function_exists('displayDate')) {
    function displayDate(DateTimeInterface|string|null $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->format(config('filament-defaults.formats.date_display_format', 'j M Y')) : null;
    }
}

if (! function_exists('displayDateTime')) {
    function displayDateTime(DateTimeInterface|string|null $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->format(config('filament-defaults.formats.datetime_display_format', 'j M Y, H:i')) : null;
    }
}
