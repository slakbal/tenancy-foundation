<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenantUser;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Events\Tenant\TenantUserCreated;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\CreateUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Globex', 'subdomain' => 'globex'], User::factory()->create(['email' => 'taken@example.test']));
    setPermissionsTeamId($this->tenant->getKey());
});

function newUserData(array $overrides = []): array
{
    return ['name' => 'Lena Learner', 'email' => 'Lena@Example.test', 'tenant_roles' => [TenantRoles::VIEW_ONLY->value], ...$overrides];
}

test('creating a user attaches them as an active member with their roles, an unusable password and no email', function () {
    Notification::fake();
    Event::fake([TenantUserCreated::class]);

    $user = app(CreateTenantUser::class)->handle($this->tenant, newUserData(), $this->owner);

    $membership = $user->tenants()->get()->firstWhere('id', $this->tenant->getKey());
    setPermissionsTeamId($this->tenant->getKey());

    expect($user->email)->toBe('lena@example.test')
        ->and($membership->pivot->status)->toBe(MemberStatus::ACTIVE)
        ->and((bool) $membership->pivot->is_owner)->toBeFalse()
        ->and((bool) $membership->pivot->is_default_tenant)->toBeTrue()
        ->and($user->fresh()->hasRole(TenantRoles::VIEW_ONLY))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->password)->not->toBeEmpty();

    Notification::assertNothingSent();
    Event::assertDispatched(TenantUserCreated::class, fn (TenantUserCreated $event): bool => $event->user->is($user)
        && $event->tenant->is($this->tenant)
        && $event->actor->is($this->owner)
        && $event->data['name'] === 'Lena Learner');
});

test('an email that already has an account, here or in another tenant, is refused and the owner role cannot be given', function () {
    expect(fn () => app(CreateTenantUser::class)->handle($this->tenant, newUserData(['email' => 'taken@example.test'])))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Invite them to this tenant');

    config(['tenancy-foundation.features.invitations' => false]);
    expect(CreateTenantUser::emailTakenMessage())->toBe(__('This email already has an account.'));
    config(['tenancy-foundation.features.invitations' => true]);
    expect(fn () => app(CreateTenantUser::class)->handle($this->tenant, newUserData(['tenant_roles' => [TenantRoles::OWNER->value]])))
        ->toThrow(AuthorizationException::class);

    expect(User::query()->where('email', 'lena@example.test')->exists())->toBeFalse();
});

test('a listener of the application takes part in the creation and can roll it back', function () {
    Event::listen(TenantUserCreated::class, function (): void {
        throw new RuntimeException('app listener failed');
    });

    expect(fn () => app(CreateTenantUser::class)->handle($this->tenant, newUserData()))->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'lena@example.test')->exists())->toBeFalse()
        ->and($this->tenant->members()->count())->toBe(1);
});

test('an owner creates a user from the create page and a view only member cannot open it', function () {
    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['email' => 'new@example.test']))
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(UserResource::getUrl('index'));

    expect(User::query()->where('email', 'new@example.test')->exists())->toBeTrue();

    Livewire::test(CreateUser::class)
        ->fillForm(newUserData(['email' => 'taken@example.test']))
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique'])
        ->assertSee(__('This email already has an account. Invite them to this tenant from the Invitations page instead.'));

    $viewer = User::factory()->create();
    $this->tenant->members()->syncWithoutDetaching([$viewer->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);
    $this->actingAs($viewer, AuthGuard::TENANT->value);

    Livewire::test(CreateUser::class)->assertForbidden();
});

test('canSignIn lets an application keep a user type out of the panel', function () {
    $user = User::factory()->create();
    $this->tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);
    $panel = Filament::getPanel('app');

    $blocked = (new class extends User
    {
        public function canSignIn(): bool
        {
            return false;
        }
    })->newFromBuilder($user->getAttributes());

    expect($user->fresh()->canAccessPanel($panel))->toBeTrue()
        ->and($blocked->canAccessPanel($panel))->toBeFalse();
});
