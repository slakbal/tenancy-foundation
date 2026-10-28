<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Actions\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Models\ContactRequest;

function contactData(array $overrides = []): array
{
    return array_merge(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there'], $overrides);
}

test('the action stores the request with the given source', function (ContactSource $source) {
    $request = app(CreateContactRequest::class)->handle(contactData(), $source);

    expect($request->fresh()->source)->toBe($source)
        ->and($request->fresh()->only(['name', 'email', 'message']))->toBe(contactData());
})->with([ContactSource::PUBLIC, ContactSource::TENANT]);

test('the source cannot be mass assigned past the action by request data', function () {
    $request = app(CreateContactRequest::class)->handle(contactData(['source' => 'tenant']), ContactSource::PUBLIC);

    expect($request->fresh()->source)->toBe(ContactSource::PUBLIC);
});

test('a request created without a source falls back to public', function () {
    DB::table('contact_requests')->insert(['ulid' => (string) Str::ulid(), 'name' => 'Old', 'email' => 'old@example.com', 'message' => 'Legacy', 'created_at' => now(), 'updated_at' => now()]);

    expect(ContactRequest::query()->first()->source)->toBe(ContactSource::PUBLIC);
});

test('the source has a label and colour for the admin ui', function (ContactSource $source, string $label, string $color) {
    expect($source->getLabel())->toBe($label)
        ->and($source->getColor())->toBe($color);
})->with([
    'public' => [ContactSource::PUBLIC, 'Public', 'gray'],
    'tenant' => [ContactSource::TENANT, 'Tenant user', 'primary'],
]);

test('each call creates one separate request', function () {
    app(CreateContactRequest::class)->handle(contactData(), ContactSource::PUBLIC);
    app(CreateContactRequest::class)->handle(contactData(['email' => 'other@example.com']), ContactSource::TENANT);

    expect(ContactRequest::query()->count())->toBe(2)
        ->and(ContactRequest::query()->where('source', 'tenant')->count())->toBe(1);
});
