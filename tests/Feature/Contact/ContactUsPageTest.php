<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Filament\Tenant\Pages\ContactUs;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;
use Spatie\Permission\Models\Role;

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

test('the page shows who the message is sent as', function () {
    Livewire::test(ContactUs::class)->assertSee(__('Sending as :name - :email', ['name' => $this->user->name, 'email' => 'jane@example.com']));
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

test('the message is required and limited in length', function () {
    Livewire::test(ContactUs::class)
        ->fillForm(['message' => ''])
        ->call('submit')
        ->assertHasFormErrors(['message' => 'required']);

    Livewire::test(ContactUs::class)
        ->fillForm(['message' => str_repeat('a', 1025)])
        ->call('submit')
        ->assertHasFormErrors(['message' => 'max']);

    Livewire::test(ContactUs::class)
        ->fillForm(['message' => str_repeat('a', 1024)])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(ContactRequest::query()->count())->toBe(1);
});

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

test('no captcha or cloudflare call is involved for a logged in user', function () {
    config(['filament-turnstile.key' => 'site-key', 'filament-turnstile.secret' => 'secret-key']);
    Http::fake();

    Livewire::test(ContactUs::class)->fillForm(['message' => 'Hello'])->call('submit')->assertHasNoFormErrors();

    Http::assertNothingSent();
    expect(ContactRequest::query()->count())->toBe(1);
});

test('every kind of tenant user can send a message, not only owners', function () {
    $member = User::factory()->create(['name' => 'View Only']);
    $this->actingAsTenantUser($member, $this->tenant);
    Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $member->assignRole(TenantRoles::VIEW_ONLY);

    Livewire::test(ContactUs::class)->fillForm(['message' => 'From a viewer'])->call('submit')->assertHasNoFormErrors();

    expect(ContactRequest::query()->sole()->name)->toBe('View Only');
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
