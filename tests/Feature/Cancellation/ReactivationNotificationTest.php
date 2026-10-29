<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\ReactivateTenant;
use Slakbal\TenancyFoundation\Events\TenantReactivated;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountReactivated;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme School', 'subdomain' => 'acme'], $this->owner);
    app(CancelTenant::class)->handle($this->tenant);
});

test('reactivating a tenant notifies its owner', function () {
    Notification::fake();

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, AccountReactivated::class);
    Notification::assertCount(1);
});

test('members who are not owners are not notified', function () {
    Notification::fake();
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertNotSentTo($member, AccountReactivated::class);
});

test('reactivating an active tenant sends nothing', function () {
    app(ReactivateTenant::class)->handle($this->tenant);
    Notification::fake();

    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertNothingSent();
});

test('the notification can be disabled or swapped through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.reactivated' => null]);
    app(ReactivateTenant::class)->handle($this->tenant);
    Notification::assertNothingSent();

    app(CancelTenant::class)->handle($this->tenant);
    $custom = new class($this->tenant) extends AccountReactivated {};
    config(['tenancy-foundation.notifications.reactivated' => $custom::class]);
    app(ReactivateTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, $custom::class);
});

test('the event alone is enough to send the notification', function () {
    Notification::fake();
    $this->tenant->forceFill(['cancelled_at' => null, 'scheduled_for_deletion_at' => null])->save();

    TenantReactivated::dispatch($this->tenant);

    Notification::assertSentTo($this->owner, AccountReactivated::class);
});

test('the notification is queued and tells the owner the account is back', function () {
    $notification = new AccountReactivated($this->tenant);
    $mail = $notification->toMail($this->owner);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via($this->owner))->toBe(['mail'])
        ->and($mail->subject)->toBe('Your Acme School account has been reactivated')
        ->and($mail->actionUrl)->toContain('acme.');
});
