<?php

use Illuminate\Support\Facades\Log;
use Slakbal\TenancyFoundation\Actions\Auth\IsDisposableEmail;
use Slakbal\TenancyFoundation\Models\DisposableDomain;

test('a listed domain is disposable only while the feature is on, and only on an exact domain match', function () {
    DisposableDomain::query()->create(['domain' => 'mailinator.com']);
    $isDisposable = fn (string $email): bool => app(IsDisposableEmail::class)->handle($email);

    expect($isDisposable('jane@mailinator.com'))->toBeFalse();

    config(['tenancy-foundation.features.disposable_emails' => true]);

    expect($isDisposable(' Jane@MAILINATOR.com '))->toBeTrue()
        ->and($isDisposable('jane@sub.mailinator.com'))->toBeFalse()
        ->and($isDisposable('jane@example.test'))->toBeFalse();
});

test('an empty list lets every address through and logs one warning', function () {
    config(['tenancy-foundation.features.disposable_emails' => true]);
    Log::spy();

    expect(app(IsDisposableEmail::class)->handle('jane@mailinator.com'))->toBeFalse()
        ->and(app(IsDisposableEmail::class)->handle('john@mailinator.com'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once()->with('Disposable Emails - List Empty');
});
