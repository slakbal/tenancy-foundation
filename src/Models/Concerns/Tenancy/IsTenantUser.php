<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

// Base Class e.g. User model should implement: HasTenants, HasDefaultTenant contracts

trait IsTenantUser
{
    use HasTenantAccess;
    use HasTenantMemberships;
    use OwnsTenants;
}
