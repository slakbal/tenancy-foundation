<?php

use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\CreateTenant;
use Slakbal\TenancyFoundation\Actions\RegisterTenantOwner;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\TenantRegistered;
use Slakbal\TenancyFoundation\Filament\Pages\EmailVerificationPrompt;
use Slakbal\TenancyFoundation\Filament\Pages\RegisterUser;
use Slakbal\TenancyFoundation\Http\Responses\RegistrationResponse;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\WelcomeToTenant;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();
});

function registrationData(array $tenant = [], array $user = []): array
{
    return [
        'tenant' => array_merge(['name' => 'Acme School', 'subdomain' => 'acme'], $tenant),
        'user' => array_merge([
            'name' => 'Jane Owner',
            'email' => 'jane@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'passwordConfirmation' => 'Str0ng-Passw0rd!',
        ], $user),
    ];
}

test('the action creates the user, the tenant, the owner membership and the owner role', function () {
    $user = app(RegisterTenantOwner::class)->handle(
        ['name' => 'Acme School', 'subdomain' => 'acme'],
        ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
    );

    $tenant = Tenant::query()->where('subdomain', 'acme')->firstOrFail();
    $membership = $user->tenants()->firstOrFail();

    expect($membership->is($tenant))->toBeTrue()
        ->and((bool) $membership->pivot->is_owner)->toBeTrue()
        ->and((bool) $membership->pivot->is_default_tenant)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    expect($user->fresh()->hasRole(TenantRoles::OWNER->value))->toBeTrue();
});

test('the action rolls back the user when the tenant cannot be created', function () {
    Tenant::factory()->create(['subdomain' => 'acme']);

    expect(fn () => app(RegisterTenantOwner::class)->handle(
        ['name' => 'Acme School', 'subdomain' => 'acme'],
        ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
    ))->toThrow(UniqueConstraintViolationException::class);

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

test('creating a tenant for an existing user without tenants makes it their default and keeps later ones non-default', function () {
    $user = User::factory()->create();

    $first = app(CreateTenant::class)->handle(['name' => 'One', 'subdomain' => 'one'], $user);
    $second = app(CreateTenant::class)->handle(['name' => 'Two', 'subdomain' => 'two'], $user);

    $pivots = $user->tenants()->get()->keyBy('subdomain');

    expect((bool) $pivots['one']->pivot->is_default_tenant)->toBeTrue()
        ->and((bool) $pivots['two']->pivot->is_default_tenant)->toBeFalse()
        ->and($first->isNot($second))->toBeTrue();
});

test('the wizard registers a tenant owner, logs them in, sends the verification email and redirects to the verification prompt', function () {
    Notification::fake();
    $data = registrationData();

    Livewire::test(RegisterUser::class)
        ->fillForm(['tenant' => $data['tenant']])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->fillForm($data['user'])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect(Tenant::query()->where('subdomain', 'acme')->exists())->toBeTrue()
        ->and($user->tenants)->toHaveCount(1)
        ->and(Filament::auth()->check())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the wizard does not advance past the tenant step with a taken, reserved or malformed subdomain', function (string $subdomain) {
    Tenant::factory()->create(['subdomain' => 'taken']);

    Livewire::test(RegisterUser::class)
        ->fillForm(['tenant' => ['name' => 'Acme', 'subdomain' => $subdomain]])
        ->goToNextWizardStep()
        ->assertHasFormErrors(['tenant.subdomain']);
})->with(['taken', 'admin', 'has_underscore', '-leading']);

test('the wizard blocks a duplicate email on the account step and creates nothing', function () {
    User::factory()->create(['email' => 'jane@example.com']);
    $data = registrationData();

    Livewire::test(RegisterUser::class)
        ->fillForm(['tenant' => $data['tenant']])
        ->goToNextWizardStep()
        ->fillForm($data['user'])
        ->call('register')
        ->assertHasFormErrors(['email']);

    expect(Tenant::query()->where('subdomain', 'acme')->exists())->toBeFalse();
});

test('the registration response sends a verified user to their default tenant domain', function () {
    $user = User::factory()->create();
    app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $user);
    $this->actingAs($user);

    $response = app(RegistrationResponse::class)->toResponse(request());

    expect($response->getTargetUrl())->toBe(appDomainScheme().'acme.'.config('tenancy-foundation.domain'));
});

test('registration dispatches TenantRegistered once and sends the welcome notification to the owner', function () {
    Event::fake([TenantRegistered::class]);

    $user = app(RegisterTenantOwner::class)->handle(
        ['name' => 'Acme School', 'subdomain' => 'acme'],
        ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
    );

    Event::assertDispatchedTimes(TenantRegistered::class, 1);
    Event::assertDispatched(TenantRegistered::class, fn (TenantRegistered $event): bool => $event->owner->is($user) && $event->tenant->subdomain === 'acme');
});

test('the welcome notification is mailed to the owner with a link to the tenant domain', function () {
    Notification::fake();

    $user = app(RegisterTenantOwner::class)->handle(
        ['name' => 'Acme School', 'subdomain' => 'acme'],
        ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
    );

    Notification::assertSentTo($user, WelcomeToTenant::class, function (WelcomeToTenant $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->actionUrl === appDomainScheme().'acme.'.config('tenancy-foundation.domain')
            && str_contains(implode(' ', $mail->introLines), 'Acme School');
    });
});

test('the welcome notification can be disabled through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.welcome' => null]);

    $user = app(RegisterTenantOwner::class)->handle(
        ['name' => 'Acme School', 'subdomain' => 'acme'],
        ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
    );

    Notification::assertNotSentTo($user, WelcomeToTenant::class);
});

test('no event or welcome notification is produced when the registration rolls back', function () {
    Event::fake([TenantRegistered::class]);
    Tenant::factory()->create(['subdomain' => 'acme']);

    try {
        app(RegisterTenantOwner::class)->handle(
            ['name' => 'Acme School', 'subdomain' => 'acme'],
            ['name' => 'Jane Owner', 'email' => 'jane@example.com', 'password' => 'secret-password'],
        );
    } catch (UniqueConstraintViolationException) {
    }

    Event::assertNotDispatched(TenantRegistered::class);
});

test('the wizard sends both the verification email and the welcome email', function () {
    Notification::fake();
    $data = registrationData();

    Livewire::test(RegisterUser::class)
        ->fillForm(['tenant' => $data['tenant']])
        ->goToNextWizardStep()
        ->fillForm($data['user'])
        ->call('register');

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertSentTo($user, WelcomeToTenant::class);
});

test('the tenant and user form components can be overridden by a subclass', function () {
    $page = new class extends RegisterUser
    {
        public function tenantComponentNames(): array
        {
            return collect($this->getTenantFormComponents())->map(fn ($component) => $component->getName())->all();
        }

        protected function getTenantFormComponents(): array
        {
            return [...parent::getTenantFormComponents(), TextInput::make('region')];
        }

        public function userComponentNames(): array
        {
            return collect($this->getUserFormComponents())->map(fn ($component) => $component->getName())->all();
        }

        protected function getUserFormComponents(): array
        {
            return [...parent::getUserFormComponents(), TextInput::make('phone')];
        }
    };

    expect($page->tenantComponentNames())->toBe(['name', 'subdomain', 'region'])
        ->and($page->userComponentNames())->toContain('email', 'phone');
});

test('the register page is wider than the default simple page', function () {
    expect((new RegisterUser)->getMaxWidth())->toBe(Width::ThreeExtraLarge);
});

test('every email sent during registration is queued', function () {
    expect(is_subclass_of(WelcomeToTenant::class, ShouldQueue::class))->toBeTrue()
        ->and(is_subclass_of(VerifyEmail::class, ShouldQueue::class))->toBeTrue();
});

test('the verification prompt tells the user to refresh once verified', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    Livewire::test(EmailVerificationPrompt::class)
        ->assertSee('refresh this page');
});
