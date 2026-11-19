<?php

namespace Slakbal\TenancyFoundation\Support;

use NumberFormatter;

class Money
{
    public static function currency(?string $currency = null): string
    {
        return strtoupper($currency ?? config('filament-defaults.formats.currency') ?? 'USD');
    }

    public static function locale(): string
    {
        return config('filament-defaults.formats.number_locale') ?? app()->getLocale();
    }

    public static function digits(?string $currency = null): int
    {
        return (new NumberFormatter(static::locale().'@currency='.static::currency($currency), NumberFormatter::CURRENCY))->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    public static function symbol(?string $currency = null): string
    {
        return (new NumberFormatter(static::locale().'@currency='.static::currency($currency), NumberFormatter::CURRENCY))->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    public static function toMinor(mixed $amount, ?string $currency = null): int
    {
        return (int) round(((float) $amount) * 10 ** static::digits($currency));
    }

    public static function toAmount(?int $minor, ?string $currency = null): float
    {
        return ($minor ?? 0) / 10 ** static::digits($currency);
    }

    public static function format(?int $minor, ?string $currency = null): string
    {
        return (new NumberFormatter(static::locale().'@currency='.static::currency($currency), NumberFormatter::CURRENCY))->formatCurrency(static::toAmount($minor, $currency), static::currency($currency));
    }
}
