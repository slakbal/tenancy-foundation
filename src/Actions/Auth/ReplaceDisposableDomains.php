<?php

namespace Slakbal\TenancyFoundation\Actions\Auth;

use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Models\DisposableDomain;

readonly class ReplaceDisposableDomains
{
    /**
     * @param  array<int, string>  $domains
     */
    public function handle(array $domains): void
    {
        DB::transaction(function () use ($domains): void {
            DisposableDomain::query()->delete();

            foreach (array_chunk($domains, 1000) as $chunk) {
                DisposableDomain::query()->insert(array_map(fn (string $domain): array => ['domain' => $domain], $chunk));
            }
        });
    }
}
