<?php

namespace Slakbal\TenancyFoundation\Http\Responses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Http\Responses\Contracts\CancelledTenantResponse as CancelledTenantResponseContract;
use Symfony\Component\HttpFoundation\Response;

class CancelledTenantResponse implements CancelledTenantResponseContract
{
    // A cancelled tenant's host looks exactly like an unknown one. Bind your own implementation to redirect instead.
    public function toResponse(Request $request, Model $tenant): Response
    {
        abort(Response::HTTP_NOT_FOUND);
    }
}
