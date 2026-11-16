<?php

namespace Slakbal\TenancyFoundation\Http\Responses\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface CancelledTenantResponse
{
    public function toResponse(Request $request, Model $tenant): Response;
}
