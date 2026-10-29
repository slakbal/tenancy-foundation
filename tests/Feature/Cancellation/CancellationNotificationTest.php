<?php

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\ReactivateTenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:30:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme School', 'subdomain' => 'acme'], $this->owner);
});

test('cancelling a tenant notifies its owner but not its other members', function () {
    Notification::fake();
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, AccountCancelled::class);
    Notification::assertNotSentTo($member, AccountCancelled::class);
    Notification::assertCount(1);
});

test('every owner is notified when there are several', function () {
    Notification::fake();
    $second = User::factory()->create();
    $this->tenant->members()->attach($second, ['is_owner' => true, 'is_default_tenant' => true]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo([$this->owner, $second], AccountCancelled::class);
});

test('the notification can be disabled or swapped through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.cancelled' => null]);
    app(CancelTenant::class)->handle($this->tenant);
    Notification::assertNothingSent();

    app(ReactivateTenant::class)->handle($this->tenant);
    $custom = new class($this->tenant) extends AccountCancelled {};
    config(['tenancy-foundation.notifications.cancelled' => $custom::class]);
    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, $custom::class);
});

test('the mail names the date and the number of days and warns that nothing can be recovered', function (int $days, string $date) {
    config(['tenancy-foundation.cancellation.grace_days' => $days]);

    $tenant = app(CancelTenant::class)->handle($this->tenant);

    $mail = (new AccountCancelled($tenant))->toMail($this->owner);
    $text = implode(' ', [$mail->subject, ...$mail->introLines]);

    expect($mail->subject)->toBe('Your Acme School account has been cancelled')
        ->and($text)->toContain('marked for deletion')
        ->and($text)->toContain($date)
        ->and($text)->toContain("{$days} days")
        ->and($text)->toContain('completely deleted and cannot be recovered');
})->with([
    'thirty days' => [30, '27 Nov 2026'],
    'seven days' => [7, '4 Nov 2026'],
]);
