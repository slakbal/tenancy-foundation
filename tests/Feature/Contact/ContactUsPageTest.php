<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\ContactUs;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->user = User::factory()->create(['name' => 'Jane Owner', 'email' => 'jane@example.com']);
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->user);
    $this->actingAsTenantUser($this->user, $this->tenant);
});

test('the page only asks for the message', function () {
    Livewire::test(ContactUs::class)
        ->assertFormFieldExists('message')
        ->assertFormFieldDoesNotExist('name')
        ->assertFormFieldDoesNotExist('email')
        ->assertFormFieldDoesNotExist('captcha');
});

test('a message is stored with the name and email of the logged in user and the tenant source', function () {
    Livewire::test(ContactUs::class)
        ->fillForm(['message' => 'Please help'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Thank you! Your message has been sent.'))
        ->assertSet('data.message', null);

    $request = ContactRequest::query()->sole();

    expect($request->name)->toBe($this->user->name)
        ->and($request->email)->toBe('jane@example.com')
        ->and($request->message)->toBe('Please help')
        ->and($request->source)->toBe(ContactSource::TENANT);
});

test('the message is required and limited in length', function (string $message, ?string $rule) {
    $component = Livewire::test(ContactUs::class)
        ->fillForm(['message' => $message])
        ->call('submit');

    $rule === null ? $component->assertHasNoFormErrors() : $component->assertHasFormErrors(['message' => $rule]);

    expect(ContactRequest::query()->count())->toBe($rule === null ? 1 : 0);
})->with([
    'empty' => ['', 'required'],
    'too long' => [str_repeat('a', 1025), 'max'],
    'at the limit' => [str_repeat('a', 1024), null],
]);

test('a name and email posted by the browser are ignored in favour of the logged in user', function () {
    Livewire::test(ContactUs::class)
        ->set('data.message', 'Sneaky')
        ->set('data.name', 'Somebody Else')
        ->set('data.email', 'evil@example.com')
        ->call('submit');

    $request = ContactRequest::query()->sole();

    expect($request->name)->toBe('Jane Owner')
        ->and($request->email)->toBe('jane@example.com');
});

test('the page is served on the tenant domain', function () {
    $this->get($this->tenantUrl($this->tenant, '/contact'))->assertOk()->assertSeeLivewire(ContactUs::class);
});

test('the contact page can be swapped through the plugin', function () {
    $panel = Filament::getPanel('app');
    $plugin = TenantPlugin::make()->usingContactPage(CustomContactUs::class);

    $plugin->register($panel);

    expect($panel->getPages())->toContain(CustomContactUs::class);
});

class CustomContactUs extends ContactUs {}
