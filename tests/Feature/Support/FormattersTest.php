<?php

use Illuminate\Support\Carbon;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;

test('displayDate follows the configured date display format', function () {
    expect(displayDate(Carbon::parse('2026-11-05 14:30:00')))->toBe('5 Nov 2026');

    config(['filament-defaults.formats.date_display_format' => 'd/m/Y']);

    expect(displayDate('2026-11-05 14:30:00'))->toBe('05/11/2026');
});

test('displayDateTime follows the configured datetime display format', function () {
    expect(displayDateTime(Carbon::parse('2026-11-05 14:30:00')))->toBe('5 Nov 2026, 14:30');

    config(['filament-defaults.formats.datetime_display_format' => 'Y-m-d H:i:s']);

    expect(displayDateTime('2026-11-05 14:30:09'))->toBe('2026-11-05 14:30:09');
});

test('the display helpers return null for empty values', function (mixed $value) {
    expect(displayDate($value))->toBeNull()->and(displayDateTime($value))->toBeNull();
})->with([null, '']);

test('the cancellation email shows the deletion date in the configured format', function () {
    $tenant = Tenant::factory()->create(['cancelled_at' => '2026-10-28 09:00:00', 'scheduled_for_deletion_at' => '2026-11-27 23:59:59']);

    $lines = collect((new AccountCancelled($tenant))->toMail($tenant)->introLines)->implode(' ');

    expect($lines)->toContain('27 Nov 2026');

    config(['filament-defaults.formats.date_display_format' => 'd/m/Y']);

    expect(collect((new AccountCancelled($tenant))->toMail($tenant)->introLines)->implode(' '))->toContain('27/11/2026');
});
