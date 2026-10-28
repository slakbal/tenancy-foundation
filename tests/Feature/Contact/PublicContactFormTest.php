<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;
use Slakbal\TenancyFoundation\Models\ContactRequest;

beforeEach(function () {
    config(['filament-turnstile.key' => 'site-key', 'filament-turnstile.secret' => 'secret-key']);
});

function fakeTurnstile(bool $success): void
{
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success, 'error-codes' => $success ? [] : ['invalid-input-response']])]);
}

test('a valid submission stores the contact request and resets the form', function () {
    fakeTurnstile(true);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there', 'captcha' => 'token'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Thank you! Your message has been sent.'))
        ->assertSet('data.name', null);

    expect(ContactRequest::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('a public submission is stored with the public source', function () {
    fakeTurnstile(true);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there', 'captcha' => 'token'])
        ->call('submit');

    expect(ContactRequest::query()->sole()->source)->toBe(ContactSource::PUBLIC);
});

test('required fields and a valid email are enforced', function () {
    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => '', 'email' => 'not-an-email', 'message' => ''])
        ->call('submit')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'email', 'message' => 'required']);

    expect(ContactRequest::query()->count())->toBe(0);
});

test('a submission without a captcha token is rejected', function () {
    fakeTurnstile(true);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there'])
        ->call('submit')
        ->assertHasFormErrors(['captcha' => 'required']);

    expect(ContactRequest::query()->count())->toBe(0);
});

test('a captcha token that cloudflare rejects blocks the submission', function () {
    fakeTurnstile(false);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there', 'captcha' => 'bad'])
        ->call('submit')
        ->assertHasFormErrors(['captcha']);

    expect(ContactRequest::query()->count())->toBe(0);
});

test('the message is limited to 1024 characters', function () {
    fakeTurnstile(true);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => str_repeat('a', 1025), 'captcha' => 'token'])
        ->call('submit')
        ->assertHasFormErrors(['message' => 'max']);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => str_repeat('a', 1024), 'captcha' => 'token'])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(ContactRequest::query()->count())->toBe(1);
});
