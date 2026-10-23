<?php

namespace Slakbal\TenancyFoundation\Support;

class PolicyResolver
{
    public static function resolve(string $panel, string $modelClass): string
    {
        $policy = class_basename($modelClass).'Policy';

        $applicationPolicy = "App\\Policies\\{$panel}\\{$policy}";

        if (class_exists($applicationPolicy)) {
            return $applicationPolicy;
        }

        $packagePolicy = "Slakbal\\TenancyFoundation\\Policies\\{$panel}\\{$policy}";

        return class_exists($packagePolicy) ? $packagePolicy : $applicationPolicy;
    }
}
