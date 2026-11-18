<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Actions\Contact\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

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

test('the action records the tenant and the user the request came from, never from request data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $other = Tenant::factory()->create();

    $request = app(CreateContactRequest::class)->handle(contactData(['tenant_id' => $other->getKey()]), ContactSource::TENANT, $tenant, $user);
    $anonymous = app(CreateContactRequest::class)->handle(contactData(), ContactSource::PUBLIC);

    expect($request->fresh()->tenant->is($tenant))->toBeTrue()
        ->and($request->fresh()->user->is($user))->toBeTrue()
        ->and($anonymous->fresh()->only(['tenant_id', 'user_id']))->toBe(['tenant_id' => null, 'user_id' => null]);
});
