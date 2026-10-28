<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\CancelTenant;
use Slakbal\TenancyFoundation\Actions\CreateTenant;
use Slakbal\TenancyFoundation\Events\TenantCancelled;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:30:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme School', 'subdomain' => 'acme'], $this->owner);
});

test('cancelling a tenant notifies its owner', function () {
    Notification::fake();

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, AccountCancelled::class);
    Notification::assertCount(1);
});

test('members who are not owners are not notified', function () {
    Notification::fake();
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertNotSentTo($member, AccountCancelled::class);
});

test('every owner is notified when there are several', function () {
    Notification::fake();
    $second = User::factory()->create();
    $this->tenant->members()->attach($second, ['is_owner' => true, 'is_default_tenant' => true]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo([$this->owner, $second], AccountCancelled::class);
});

test('cancelling twice only notifies once', function () {
    Notification::fake();

    app(CancelTenant::class)->handle($this->tenant);
    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertCount(1);
});

test('the notification can be disabled through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.cancelled' => null]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertNothingSent();
});

test('the notification class can be swapped through config', function () {
    Notification::fake();
    $custom = new class($this->tenant) extends AccountCancelled {};
    config(['tenancy-foundation.notifications.cancelled' => $custom::class]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, $custom::class);
});

test('the event alone is enough to send the notification', function () {
    Notification::fake();
    $this->tenant->forceFill(['cancelled_at' => now(), 'scheduled_for_deletion_at' => now()->addDays(30)->endOfDay()])->save();

    TenantCancelled::dispatch($this->tenant);

    Notification::assertSentTo($this->owner, AccountCancelled::class);
});

test('the mail is queued', function () {
    expect(is_subclass_of(AccountCancelled::class, ShouldQueue::class))->toBeTrue();
});

test('the mail names the date and the number of days and warns that nothing can be recovered', function () {
    $tenant = app(CancelTenant::class)->handle($this->tenant);

    $mail = (new AccountCancelled($tenant))->toMail($this->owner);
    $text = implode(' ', [$mail->subject, ...$mail->introLines]);

    expect($mail->subject)->toBe('Your Acme School account has been cancelled')
        ->and($text)->toContain('marked for deletion')
        ->and($text)->toContain('27 Nov 2026')
        ->and($text)->toContain('30 days')
        ->and($text)->toContain('completely deleted and cannot be recovered');
});

test('the days in the mail follow the configured grace period', function () {
    config(['tenancy-foundation.cancellation.grace_days' => 7]);

    $tenant = app(CancelTenant::class)->handle($this->tenant);

    expect(implode(' ', (new AccountCancelled($tenant))->toMail($this->owner)->introLines))->toContain('7 days')->toContain('4 Nov 2026');
});
