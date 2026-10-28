<?php

namespace Slakbal\TenancyFoundation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Models\ContactRequest;

readonly class RespondToContactRequest
{
    public function handle(ContactRequest $request, string $response, string $respondedBy): ContactRequest
    {
        throw_unless($request->canBeRespondedTo(), DomainException::class, __('This contact request cannot be responded to.'));

        DB::transaction(function () use ($request, $response, $respondedBy): void {
            $request->forceFill([
                'response' => $response,
                'responded_at' => now(),
                'responded_by' => $respondedBy,
                'status' => ContactStatus::COMPLETED,
                'closed_at' => $request->closed_at ?? now(),
            ])->save();
        });

        $notification = config('tenancy-foundation.notifications.contact_response');

        if ($notification !== null) {
            Notification::route('mail', [$request->email => $request->name])->notify(new $notification($request));
        }

        return $request;
    }
}
