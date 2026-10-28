<?php

namespace Slakbal\TenancyFoundation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Models\ContactRequest;

readonly class ChangeContactRequestStatus
{
    public function handle(ContactRequest $request, ContactStatus $status): ContactRequest
    {
        throw_if($status === ContactStatus::DISMISSED && $request->isResponded(), DomainException::class, __('A request that has been responded to cannot be dismissed.'));

        return DB::transaction(function () use ($request, $status): ContactRequest {
            $request->forceFill([
                'status' => $status,
                'closed_at' => $status->isClosed() ? ($request->closed_at ?? now()) : null,
            ])->save();

            return $request;
        });
    }
}
