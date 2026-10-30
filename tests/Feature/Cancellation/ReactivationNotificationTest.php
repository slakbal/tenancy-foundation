<?php

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\ReactivateTenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountReactivated;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme School', 'subdomain' => 'acme'], $this->owner);
    app(CancelTenant::class)->handle($this->tenant);
});

test('reactivating a tenant notifies every owner but not its other members', function () {
    Notification::fake();
    $second = User::factory()->create();
    $this->tenant->members()->attach($second, ['is_owner' => true, 'is_default_tenant' => true]);
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertSentTo([$this->owner, $second], AccountReactivated::class);
    Notification::assertNotSentTo($member, AccountReactivated::class);
    Notification::assertCount(2);
});

test('the notification can be disabled through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.reactivated' => null]);

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertNothingSent();
});

test('the notification can be swapped through config', function () {
    Notification::fake();
    $custom = new class($this->tenant) extends AccountReactivated {};
    config(['tenancy-foundation.notifications.reactivated' => $custom::class]);

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, $custom::class);
});

test('the notification tells the owner the account is back', function () {
    $mail = (new AccountReactivated($this->tenant))->toMail($this->owner);

    expect($mail->subject)->toBe('Your Acme School account has been reactivated')
        ->and($mail->actionUrl)->toContain('acme.');
});
