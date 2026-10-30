<?php

namespace Slakbal\TenancyFoundation\Actions\Contact;

use DomainException;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestAnswered;
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

        ContactRequestAnswered::dispatch($request);

        return $request;
    }
}
