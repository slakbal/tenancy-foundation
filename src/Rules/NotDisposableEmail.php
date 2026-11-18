<?php

namespace Slakbal\TenancyFoundation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Slakbal\TenancyFoundation\Actions\Auth\IsDisposableEmail;

class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(IsDisposableEmail::class)->handle($value)) {
            $fail(__('Disposable email addresses are not allowed.'));
        }
    }
}
