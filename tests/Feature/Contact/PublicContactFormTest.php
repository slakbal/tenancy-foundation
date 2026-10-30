<?php

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;
use Slakbal\TenancyFoundation\Models\ContactRequest;

class CustomPublicContactForm extends PublicContactForm
{
    protected static function fields(): array
    {
        return [TextInput::make('phone'), ...parent::fields()];
    }
}

beforeEach(function () {
    config(['filament-turnstile.key' => 'site-key', 'filament-turnstile.secret' => 'secret-key']);
});

function fakeTurnstile(bool $success): void
{
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success, 'error-codes' => $success ? [] : ['invalid-input-response']])]);
}

test('the contact page renders the filament styled form', function () {
    $this->get(route('public.contact'))
        ->assertOk()
        ->assertSee('fi-simple-header-heading', false)
        ->assertSee(__('Get in touch'));
});

test('the public contact form renders the default fields', function () {
    Livewire::test(PublicContactForm::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('message')
        ->assertFormFieldDoesNotExist('phone');
});

test('a subclass can change the fields', function () {
    Livewire::test(CustomPublicContactForm::class)
        ->assertFormFieldExists('phone')
        ->assertFormFieldExists('message');
});

test('a valid submission stores the contact request with the public source and resets the form', function () {
    fakeTurnstile(true);

    Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there', 'captcha' => 'token'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Thank you! Your message has been sent.'))
        ->assertSet('data.name', null);

    $request = ContactRequest::query()->sole();

    expect($request->email)->toBe('jane@example.com')
        ->and($request->source)->toBe(ContactSource::PUBLIC);
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

test('the message is limited to 1024 characters', function (int $length, bool $accepted) {
    fakeTurnstile(true);

    $component = Livewire::test(PublicContactForm::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => str_repeat('a', $length), 'captcha' => 'token'])
        ->call('submit');

    $accepted ? $component->assertHasNoFormErrors() : $component->assertHasFormErrors(['message' => 'max']);

    expect(ContactRequest::query()->count())->toBe($accepted ? 1 : 0);
})->with([
    'too long' => [1025, false],
    'at the limit' => [1024, true],
]);
