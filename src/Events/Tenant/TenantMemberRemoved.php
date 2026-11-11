<?php

namespace Slakbal\TenancyFoundation\Events\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TenantMemberRemoved
{
    use Dispatchable;

    /**
     * @param  bool  $accountDeleted  true when the tenant was the user's only one, so the account was soft deleted instead of detached
     */
    public function __construct(public Model $tenant, public Model $user, public ?Model $actor, public bool $accountDeleted) {}

    public function leftVoluntarily(): bool
    {
        return $this->actor?->is($this->user) === true;
    }
}
