<?php

use Slakbal\TenancyFoundation\Notifications\Tenant\AccountCancelled;
use Slakbal\TenancyFoundation\TenancyFoundationServiceProvider;

test('config sections published by an older version still receive the new default keys', function () {
    config([
        'tenancy-foundation.notifications' => ['welcome' => 'App\Notifications\CustomWelcome'],
        'tenancy-foundation.cancellation' => ['grace_days' => 7],
    ]);

    (new TenancyFoundationServiceProvider(app()))->register();

    expect(config('tenancy-foundation.notifications.welcome'))->toBe('App\Notifications\CustomWelcome')
        ->and(config('tenancy-foundation.notifications.cancelled'))->toBe(AccountCancelled::class)
        ->and(config('tenancy-foundation.cancellation.grace_days'))->toBe(7)
        ->and(config('tenancy-foundation.cancellation.schedule_deletion'))->toBeTrue()
        ->and(config('tenancy-foundation.cancellation.deletion_time'))->toBe('01:00');
});

test('a section that explicitly disables a notification keeps it disabled', function () {
    config(['tenancy-foundation.notifications' => ['welcome' => null, 'cancelled' => null]]);

    (new TenancyFoundationServiceProvider(app()))->register();

    expect(config('tenancy-foundation.notifications.welcome'))->toBeNull()
        ->and(config('tenancy-foundation.notifications.cancelled'))->toBeNull();
});
