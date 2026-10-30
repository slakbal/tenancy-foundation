<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Contact\ChangeContactRequestStatus;
use Slakbal\TenancyFoundation\Actions\Contact\RespondToContactRequest;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestAnswered;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages\EditContactRequest;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages\ListContactRequests;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Widgets\ContactRequestStats;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Notifications\Contact\ContactRequestResponded;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;
use Spatie\Permission\Models\Permission;

function contactAdminWith(AdminPermissions ...$permissions): Admin
{
    $admin = Admin::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->travelTo('2026-10-28 09:30:00');
});

test('an admin without the view permission cannot see the resource', function () {
    $this->actingAs(contactAdminWith(), AuthGuard::ADMIN->value);

    expect(ContactRequestResource::canViewAny())->toBeFalse();
});

test('an admin with the view permission lists requests and filters them', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY), AuthGuard::ADMIN->value);

    $new = ContactRequest::factory()->create();
    $tenant = ContactRequest::factory()->fromTenant()->create();
    $done = ContactRequest::factory()->closed()->create();

    Livewire::test(ListContactRequests::class)
        ->assertTableColumnDoesNotExist('message')
        ->assertTableColumnExists('responded_by')
        ->removeTableFilters()
        ->assertCanSeeTableRecords([$new, $tenant, $done])
        ->filterTable('source', ContactSource::TENANT->value)
        ->assertCanSeeTableRecords([$tenant])
        ->assertCanNotSeeTableRecords([$new, $done])
        ->removeTableFilters()
        ->filterTable('status', [ContactStatus::COMPLETED->value])
        ->assertCanSeeTableRecords([$done])
        ->assertCanNotSeeTableRecords([$new, $tenant]);
});

test('the responded filter separates answered and unanswered requests', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY), AuthGuard::ADMIN->value);

    $open = ContactRequest::factory()->create();
    $answered = ContactRequest::factory()->responded()->create();

    Livewire::test(ListContactRequests::class)
        ->filterTable('responded', true)
        ->assertCanSeeTableRecords([$answered])->assertCanNotSeeTableRecords([$open])
        ->filterTable('responded', false)
        ->assertCanSeeTableRecords([$open])->assertCanNotSeeTableRecords([$answered]);
});

test('the navigation badge counts new requests only', function () {
    ContactRequest::factory()->count(2)->create();
    ContactRequest::factory()->closed()->create();

    expect(ContactRequestResource::getNavigationBadge())->toBe('2');

    ContactRequest::query()->delete();

    expect(ContactRequestResource::getNavigationBadge())->toBeNull();
});

test('the message is read only on the edit page', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create(['message' => 'Original']);

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->assertFormSet(['message' => 'Original'])
        ->assertFormFieldDisabled('message');

    expect($request->fresh()->message)->toBe('Original');
});

test('dismiss and reopen change the status and track when the request was closed', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    $page = Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('dismiss')->assertActionHidden('reopen')->assertActionDoesNotExist('complete');

    $page->callAction('dismiss')->assertActionHidden('dismiss');
    expect($request->fresh()->status)->toBe(ContactStatus::DISMISSED)->and($request->fresh()->closed_at->toDateTimeString())->toBe('2026-10-28 09:30:00');

    $page->callAction('reopen');
    expect($request->fresh()->status)->toBe(ContactStatus::NEW)->and($request->fresh()->closed_at)->toBeNull();
});

test('the edit page needs the update permission', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_RESPOND), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])->assertForbidden();
});

test('responding stores the response and the responder, completes the request and queues one email to the requestor', function () {
    Notification::fake();
    $admin = contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE, AdminPermissions::CONTACT_REQUEST_RESPOND);
    $this->actingAs($admin, AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm(['response' => 'We fixed it.'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Response sent.'))
        ->assertRedirect(ContactRequestResource::getUrl());

    $fresh = $request->fresh();
    expect($fresh->response)->toBe('We fixed it.')
        ->and($fresh->responded_by)->toBe($admin->name)
        ->and($fresh->responded_at)->not->toBeNull()
        ->and($fresh->status)->toBe(ContactStatus::COMPLETED)
        ->and($fresh->closed_at)->not->toBeNull();

    Notification::assertSentOnDemand(ContactRequestResponded::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['jane@example.com' => 'Jane Doe'] && $notification->contactRequest->is($request));
    Notification::assertCount(1);
});

test('the responder is kept as plain text when the admin is deleted', function () {
    $admin = Admin::factory()->create(['name' => 'Gone Admin']);
    $request = ContactRequest::factory()->create();
    Notification::fake();

    app(RespondToContactRequest::class)->handle($request, 'Hello', $admin->name);
    $admin->forceDelete();

    expect($request->fresh()->responded_by)->toBe('Gone Admin');
});

test('the response email is queued and quotes the original message', function () {
    $request = ContactRequest::factory()->responded('Line one<script>')->create(['message' => 'My question']);
    $notification = new ContactRequestResponded($request);

    $mail = $notification->toMail($request);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($mail->subject)->toBe('Re: your message to '.config('app.name'))
        ->and(collect($mail->introLines)->map(fn ($line): string => (string) $line)->implode(' '))->toContain('Line one&lt;script&gt;')->toContain('My question');
});

test('a response is required', function () {
    Notification::fake();
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE, AdminPermissions::CONTACT_REQUEST_RESPOND), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm(['response' => ''])
        ->call('save')
        ->assertHasFormErrors(['response' => 'required']);

    expect($request->fresh()->isResponded())->toBeFalse();
    Notification::assertNothingSent();
});

test('responding needs the respond permission', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm(['response' => 'Sneaky'])
        ->call('save')
        ->assertForbidden();

    expect($request->fresh()->isResponded())->toBeFalse();
});

test('delete and bulk delete remove requests immediately and need the delete permission', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_DELETE), AuthGuard::ADMIN->value);
    [$one, $two, $three] = ContactRequest::factory()->count(3)->create()->all();

    Livewire::test(ListContactRequests::class)
        ->callAction(TestAction::make('delete')->table($one))
        ->callTableBulkAction('delete', [$two, $three]);

    expect(ContactRequest::query()->count())->toBe(0);
});

test('without the delete permission nothing can be deleted', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    Livewire::test(ListContactRequests::class)->assertActionHidden(TestAction::make('delete')->table($request));

    expect(ContactRequest::query()->count())->toBe(1);
});

test('only closed requests older than the configured days are pruned', function () {
    config(['tenancy-foundation.contact_requests.prune_after_days' => 30]);

    $oldCompleted = ContactRequest::factory()->closed(ContactStatus::COMPLETED, now()->subDays(31))->create();
    $oldDismissed = ContactRequest::factory()->closed(ContactStatus::DISMISSED, now()->subDays(30))->create();
    $recent = ContactRequest::factory()->closed(ContactStatus::COMPLETED, now()->subDays(29))->create();
    $oldNew = ContactRequest::factory()->create(['created_at' => now()->subYear()]);

    Artisan::call('model:prune', ['--model' => [ContactRequest::class]]);

    expect(ContactRequest::query()->pluck('id')->all())->toEqualCanonicalizing([$recent->id, $oldNew->id])
        ->and(ContactRequest::query()->find($oldCompleted->id))->toBeNull()
        ->and(ContactRequest::query()->find($oldDismissed->id))->toBeNull();
});

test('pruning is disabled when the configured days are zero', function (mixed $days) {
    config(['tenancy-foundation.contact_requests.prune_after_days' => $days]);
    ContactRequest::factory()->closed(ContactStatus::COMPLETED, now()->subYears(3))->create();

    Artisan::call('model:prune', ['--model' => [ContactRequest::class]]);

    expect(ContactRequest::query()->count())->toBe(1);
})->with([0, null]);

test('the prune command is scheduled for app models and the package models, once across servers', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'model:prune'))
        ->values();

    expect($events)->toHaveCount(2)
        ->and($events->every(fn ($event): bool => $event->expression === '0 0 * * *' && $event->onOneServer && $event->withoutOverlapping))->toBeTrue()
        ->and($events->contains(fn ($event): bool => ! str_contains($event->command, '--model')))->toBeTrue()
        ->and($events->contains(fn ($event): bool => str_contains($event->command, '--model') && str_contains($event->command, ContactRequest::class)))->toBeTrue();
});

test('the resource can be swapped through the admin plugin', function () {
    $panel = Filament::getPanel('admin');

    AdminPlugin::make()->usingContactRequestResource(CustomContactRequestResource::class)->register($panel);

    expect($panel->getResources())->toContain(CustomContactRequestResource::class);
});

class CustomContactRequestResource extends ContactRequestResource {}

test('the source and status are shown as badges in the page header', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->fromTenant()->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->assertSee('fi-badge', false)
        ->assertSeeInOrder([ContactSource::TENANT->getLabel(), ContactStatus::NEW->getLabel()])
        ->assertFormFieldDoesNotExist('source')
        ->assertFormFieldDoesNotExist('status');
});

test('a dismissed or completed request cannot be responded to until it is reopened', function (ContactStatus $status) {
    Notification::fake();
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE, AdminPermissions::CONTACT_REQUEST_RESPOND), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->closed($status)->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->assertFormFieldDisabled('response')
        ->call('save')
        ->assertForbidden();

    expect($request->fresh()->isResponded())->toBeFalse()
        ->and(fn () => app(RespondToContactRequest::class)->handle($request, 'Hi', 'Someone'))->toThrow(DomainException::class);

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('reopen')
        ->assertFormFieldEnabled('response');

    Notification::assertNothingSent();
})->with([ContactStatus::DISMISSED, ContactStatus::COMPLETED]);

test('a responded request stays locked even after it is reopened', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE, AdminPermissions::CONTACT_REQUEST_RESPOND), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->responded()->closed()->create();

    Livewire::test(EditContactRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('reopen')
        ->assertFormFieldDisabled('response')
        ->call('save')
        ->assertForbidden();
});

test('the table shows only new requests by default', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY), AuthGuard::ADMIN->value);
    $new = ContactRequest::factory()->create();
    $dismissed = ContactRequest::factory()->closed(ContactStatus::DISMISSED)->create();
    $completed = ContactRequest::factory()->closed()->create();

    Livewire::test(ListContactRequests::class)
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$dismissed, $completed])
        ->removeTableFilters()
        ->assertCanSeeTableRecords([$new, $dismissed, $completed]);
});

test('the stats widget counts new, responded and dismissed requests inside the prune window', function () {
    config(['tenancy-foundation.contact_requests.prune_after_days' => 30]);
    ContactRequest::factory()->count(2)->create();
    ContactRequest::factory()->responded()->closed()->create();
    ContactRequest::factory()->closed(ContactStatus::DISMISSED)->count(3)->create();
    ContactRequest::factory()->create(['created_at' => now()->subDays(31)]);
    ContactRequest::factory()->closed(ContactStatus::DISMISSED)->create(['created_at' => now()->subDays(31)]);
    ContactRequest::factory()->responded()->closed()->create(['created_at' => now()->subDays(31)]);

    $stats = Livewire::test(ContactRequestStats::class)->instance();
    $stats = (fn (): array => $this->getStats())->call($stats);

    expect(collect($stats)->mapWithKeys(fn ($stat): array => [(string) $stat->getLabel() => $stat->getValue()])->all())
        ->toBe(['New' => 2, 'Responded' => 1, 'Dismissed' => 3])
        ->and((string) $stats[0]->getDescription())->toBe('Last 30 days');
});

test('the stats widget covers all time when pruning is off', function () {
    config(['tenancy-foundation.contact_requests.prune_after_days' => 0]);
    ContactRequest::factory()->create(['created_at' => now()->subYears(2)]);
    ContactRequest::factory()->create();

    $stats = Livewire::test(ContactRequestStats::class)->instance();
    $stats = (fn (): array => $this->getStats())->call($stats);

    expect($stats[0]->getValue())->toBe(2)->and((string) $stats[0]->getDescription())->toBe('All time');
});

test('the stats widget is shown on the list page', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY), AuthGuard::ADMIN->value);

    Livewire::test(ListContactRequests::class)->assertSeeLivewire(ContactRequestStats::class);
});

test('bulk dismiss dismisses the selected requests and keeps the original closed date', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    [$one, $two] = ContactRequest::factory()->count(2)->create()->all();
    $already = ContactRequest::factory()->closed(ContactStatus::DISMISSED, now()->subDays(5))->create();
    $untouched = ContactRequest::factory()->create();

    Livewire::test(ListContactRequests::class)
        ->removeTableFilters()
        ->callTableBulkAction('dismiss', [$one, $two])
        ->assertNotified(__('Selected requests dismissed.'))
        ->callTableBulkAction('dismiss', [$already]);

    expect($one->fresh()->status)->toBe(ContactStatus::DISMISSED)
        ->and($one->fresh()->closed_at->toDateTimeString())->toBe('2026-10-28 09:30:00')
        ->and($two->fresh()->status)->toBe(ContactStatus::DISMISSED)
        ->and($already->fresh()->closed_at->toDateTimeString())->toBe('2026-10-23 09:30:00')
        ->and($untouched->fresh()->status)->toBe(ContactStatus::NEW);
});

test('bulk dismiss needs the update permission', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_DELETE), AuthGuard::ADMIN->value);
    $request = ContactRequest::factory()->create();

    Livewire::test(ListContactRequests::class)->assertTableBulkActionHidden('dismiss');

    expect($request->fresh()->status)->toBe(ContactStatus::NEW);
});

test('a request that was responded to cannot be dismissed', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_VIEW, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $completed = ContactRequest::factory()->responded()->closed()->create();
    $reopened = ContactRequest::factory()->responded()->create();

    Livewire::test(EditContactRequest::class, ['record' => $completed->getRouteKey()])->assertActionHidden('dismiss');
    Livewire::test(EditContactRequest::class, ['record' => $reopened->getRouteKey()])->assertActionHidden('dismiss');

    foreach ([$completed, $reopened] as $request) {
        expect(fn () => app(ChangeContactRequestStatus::class)->handle($request, ContactStatus::DISMISSED))->toThrow(DomainException::class)
            ->and(Gate::forUser(auth('admin')->user())->denies('dismiss', $request))->toBeTrue();
    }

    expect($completed->fresh()->status)->toBe(ContactStatus::COMPLETED);
});

test('bulk dismiss leaves responded requests untouched', function () {
    $this->actingAs(contactAdminWith(AdminPermissions::CONTACT_REQUEST_VIEW_ANY, AdminPermissions::CONTACT_REQUEST_UPDATE), AuthGuard::ADMIN->value);
    $open = ContactRequest::factory()->create();
    $responded = ContactRequest::factory()->responded()->closed()->create();

    Livewire::test(ListContactRequests::class)
        ->removeTableFilters()
        ->callTableBulkAction('dismiss', [$open, $responded]);

    expect($open->fresh()->status)->toBe(ContactStatus::DISMISSED)
        ->and($responded->fresh()->status)->toBe(ContactStatus::COMPLETED);
});

test('responding dispatches the answered event after the response is stored', function () {
    Event::fake([ContactRequestAnswered::class]);
    $request = ContactRequest::factory()->create();

    app(RespondToContactRequest::class)->handle($request, 'Done', 'Agent');

    Event::assertDispatchedTimes(ContactRequestAnswered::class, 1);
    Event::assertDispatched(ContactRequestAnswered::class, fn ($event): bool => $event->contactRequest->is($request) && $event->contactRequest->response === 'Done');
});

test('the response email is sent by the listener and can be switched off through config', function () {
    Notification::fake();
    $request = ContactRequest::factory()->responded()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    config(['tenancy-foundation.notifications.contact_response' => null]);
    event(new ContactRequestAnswered($request));
    Notification::assertNothingSent();

    config(['tenancy-foundation.notifications.contact_response' => ContactRequestResponded::class]);
    event(new ContactRequestAnswered($request));
    Notification::assertSentOnDemandTimes(ContactRequestResponded::class, 1);
});

test('a failed response never dispatches the event', function () {
    Event::fake([ContactRequestAnswered::class]);
    $request = ContactRequest::factory()->responded()->create();

    expect(fn () => app(RespondToContactRequest::class)->handle($request, 'again', 'Agent'))->toThrow(DomainException::class);

    Event::assertNotDispatched(ContactRequestAnswered::class);
});
