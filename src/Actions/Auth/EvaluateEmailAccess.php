<?php

namespace Slakbal\TenancyFoundation\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;
use Slakbal\TenancyFoundation\Enums\EmailAccess;

readonly class EvaluateEmailAccess
{
    public function handle(Model $tenant, string $email): EmailAccess
    {
        $domain = Str::lower(Str::afterLast($email, '@'));

        $ruleType = $tenant->domainRules()->where('domain', $domain)->value('type');

        return match ($tenant->access_policy) {
            AccessPolicy::ALLOW_LIST_ONLY => $ruleType === DomainRuleType::ALLOW ? EmailAccess::ALLOWED_BY_RULE : EmailAccess::BLOCKED_BY_POLICY,
            default => $ruleType === DomainRuleType::BLOCK ? EmailAccess::BLOCKED_BY_RULE : EmailAccess::ALLOWED_BY_POLICY,
        };
    }
}
