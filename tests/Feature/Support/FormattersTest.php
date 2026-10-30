<?php

use Illuminate\Support\Carbon;

test('the display helpers follow the configured display format', function (string $helper, string $configKey, string $value, string $default, string $format, string $configured) {
    expect($helper(Carbon::parse($value)))->toBe($default);

    config(["filament-defaults.formats.{$configKey}" => $format]);

    expect($helper($value))->toBe($configured);
})->with([
    'date' => ['displayDate', 'date_display_format', '2026-11-05 14:30:09', '5 Nov 2026', 'd/m/Y', '05/11/2026'],
    'datetime' => ['displayDateTime', 'datetime_display_format', '2026-11-05 14:30:09', '5 Nov 2026, 14:30', 'Y-m-d H:i:s', '2026-11-05 14:30:09'],
]);

test('the display helpers return null for empty values', function (mixed $value) {
    expect(displayDate($value))->toBeNull()->and(displayDateTime($value))->toBeNull();
})->with([null, '']);
