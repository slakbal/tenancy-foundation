<?php

namespace Slakbal\TenancyFoundation\Actions\Contact;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestReceived;
use Slakbal\TenancyFoundation\Models\ContactRequest;

readonly class CreateContactRequest
{
    /**
     * @param  array{name: string, email: string, message: string}  $data
     */
    public function handle(array $data, ContactSource $source, ?Model $tenant = null, ?Model $user = null): ContactRequest
    {
        $model = config('tenancy-foundation.models.contact_request');

        $request = DB::transaction(fn (): ContactRequest => $model::create([
            ...$data,
            'source' => $source,
            'tenant_id' => $tenant?->getKey(),
            'user_id' => $user?->getKey(),
        ]));

        ContactRequestReceived::dispatch($request);

        return $request;
    }
}
