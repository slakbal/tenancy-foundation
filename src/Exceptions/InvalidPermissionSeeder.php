<?php

namespace Slakbal\TenancyFoundation\Exceptions;

use RuntimeException;

class InvalidPermissionSeeder extends RuntimeException
{
    public static function notASeeder(string $configured, string $expected, string $configKey): self
    {
        return new self(__('The class ":configured" set in config :key must be :expected or a subclass of it.', [
            'configured' => $configured,
            'expected' => $expected,
            'key' => $configKey,
        ]));
    }
}
