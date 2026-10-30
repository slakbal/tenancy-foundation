<?php

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:30:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme School', 'subdomain' => 'acme'], $this->owner);
});

test('cancelling a tenant notifies every owner but not its other members', function () {
    Notification::fake();
    $second = User::factory()->create();
    $this->tenant->members()->attach($second, ['is_owner' => true, 'is_default_tenant' => true]);
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo([$this->owner, $second], AccountCancelled::class);
    Notification::assertNotSentTo($member, AccountCancelled::class);
    Notification::assertCount(2);
});

test('the notification can be disabled through config', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.cancelled' => null]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertNothingSent();
});

test('the notification can be swapped through config', function () {
    Notification::fake();
    $custom = new class($this->tenant) extends AccountCancelled {};
    config(['tenancy-foundation.notifications.cancelled' => $custom::class]);

    app(CancelTenant::class)->handle($this->tenant);

    Notification::assertSentTo($this->owner, $custom::class);
});

test('the mail names the date and the number of days and warns that nothing can be recovered', function (int $days, ?string $format, string $date) {
    config(['tenancy-foundation.cancellation.grace_days' => $days]);

    if ($format !== null) {
        config(['filament-defaults.formats.date_display_format' => $format]);
    }

    $tenant = app(CancelTenant::class)->handle($this->tenant);

    $mail = (new AccountCancelled($tenant))->toMail($this->owner);
    $text = implode(' ', [$mail->subject, ...$mail->introLines]);

    expect($mail->subject)->toBe('Your Acme School account has been cancelled')
        ->and($text)->toContain('marked for deletion')
        ->and($text)->toContain($date)
        ->and($text)->toContain("{$days} days")
        ->and($text)->toContain('completely deleted and cannot be recovered');
})->with([
    'thirty days' => [30, null, '27 Nov 2026'],
    'seven days' => [7, null, '4 Nov 2026'],
    'configured date format' => [30, 'd/m/Y', '27/11/2026'],
]);
