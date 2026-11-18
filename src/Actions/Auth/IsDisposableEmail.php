<?php

namespace Slakbal\TenancyFoundation\Actions\Auth;

use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Models\DisposableDomain;

readonly class IsDisposableEmail
{
    public function handle(string $email): bool
    {
        if (! config('tenancy-foundation.features.disposable_emails')) {
            return false;
        }

        $domain = Str::lower(trim(Str::afterLast($email, '@')));

        if (DisposableDomain::query()->where('domain', $domain)->exists()) {
            return true;
        }

        static::warnWhenListIsEmpty();

        return false;
    }

    // An empty list lets every address through, so say so, but only once per request.
    protected static function warnWhenListIsEmpty(): void
    {
        once(function (): void {
            if (! DisposableDomain::query()->exists()) {
                logger()->warning('Disposable Emails - List Empty');
            }
        });
    }
}
