<?php

namespace Slakbal\TenancyFoundation\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;
use Slakbal\TenancyFoundation\Enums\EmailAccess;

readonly class EvaluateEmailAccess
{
    public function __construct(private IsDisposableEmail $isDisposableEmail) {}

    public function handle(Model $tenant, string $email): EmailAccess
    {
        $email = Str::lower(trim($email));
        $domain = Str::afterLast($email, '@');

        $rules = $tenant->domainRules()->whereIn('value', array_unique([$email, $domain]))->pluck('type', 'value');

        // An address rule is more specific than a rule for its domain, so it wins.
        $ruleType = $rules->get($email) ?? $rules->get($domain);

        if ($ruleType !== null) {
            return $ruleType === DomainRuleType::ALLOW ? EmailAccess::ALLOWED_BY_RULE : EmailAccess::BLOCKED_BY_RULE;
        }

        if ($this->isDisposableEmail->handle($email)) {
            return EmailAccess::BLOCKED_DISPOSABLE;
        }

        return $tenant->access_policy === AccessPolicy::ALLOW_LIST_ONLY ? EmailAccess::BLOCKED_BY_POLICY : EmailAccess::ALLOWED_BY_POLICY;
    }
}
