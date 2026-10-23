<?php

use Illuminate\Support\Uri;

if (! function_exists('appUrl')) {
    function appUrl(): Uri
    {
        return once(fn () => Uri::of(config('app.url')));
    }
}

if (! function_exists('appDomainScheme')) {
    function appDomainScheme(): string
    {
        return once(fn () => appUrl()->scheme().'://');
    }
}

if (! function_exists('appDomainHost')) {
    function appDomainHost(): string
    {
        return once(fn () => appUrl()->host());
    }
}
