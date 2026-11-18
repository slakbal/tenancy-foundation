<?php

namespace Slakbal\TenancyFoundation\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Actions\Auth\ReplaceDisposableDomains;
use UnexpectedValueException;

class RefreshDisposableDomainsCommand extends Command
{
    protected $signature = 'tenancy-foundation:refresh-disposable-domains';

    protected $description = 'Download the list of disposable email domains and replace the stored list (the old list stays when the download fails)';

    public function handle(ReplaceDisposableDomains $replaceDisposableDomains): int
    {
        try {
            $domains = $this->fetchDomains(config('tenancy-foundation.disposable_emails.source'));
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            report($exception);
            $this->components->warn('The disposable email domains were not refreshed, the old list is kept: '.$exception->getMessage());

            return self::FAILURE;
        }

        $replaceDisposableDomains->handle($domains);

        $this->components->info(count($domains).' disposable email domains stored.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function fetchDomains(string $source): array
    {
        $response = Http::timeout(30)->get($source)->throw()->json();

        if (! is_array($response) || ! array_is_list($response) || array_filter($response, fn (mixed $domain): bool => ! is_string($domain)) !== []) {
            throw new UnexpectedValueException("The response of {$source} is not a JSON array of domains.");
        }

        $domains = collect($response)
            ->map(fn (string $domain): string => Str::lower(trim($domain)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($domains === []) {
            throw new UnexpectedValueException("The response of {$source} has no domains.");
        }

        return $domains;
    }
}
