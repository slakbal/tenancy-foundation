<?php

use Illuminate\Support\Facades\Http;
use Slakbal\TenancyFoundation\Console\RefreshDisposableDomainsCommand;
use Slakbal\TenancyFoundation\Models\DisposableDomain;

function storedDisposableDomains(): array
{
    return DisposableDomain::query()->orderBy('domain')->pluck('domain')->all();
}

test('a valid list replaces the stored domains, normalised and without duplicates', function () {
    DisposableDomain::query()->create(['domain' => 'old.test']);
    Http::fake([config('tenancy-foundation.disposable_emails.source') => Http::response([' Mailinator.com ', 'mailinator.com', 'yopmail.com', ''])]);

    $this->artisan(RefreshDisposableDomainsCommand::class)->assertSuccessful();

    expect(storedDisposableDomains())->toBe(['mailinator.com', 'yopmail.com']);
});

test('a failed, invalid or empty download keeps the stored list', function (mixed $response) {
    DisposableDomain::query()->create(['domain' => 'old.test']);
    Http::fake([config('tenancy-foundation.disposable_emails.source') => $response]);

    $this->artisan(RefreshDisposableDomainsCommand::class)->assertFailed();

    expect(storedDisposableDomains())->toBe(['old.test']);
})->with([
    'server error' => fn () => Http::response('down', 500),
    'not json' => fn () => Http::response('<html></html>'),
    'an object' => fn () => Http::response(['domains' => ['a.test']]),
    'not strings' => fn () => Http::response(['a.test', 42]),
    'empty' => fn () => Http::response([]),
]);
