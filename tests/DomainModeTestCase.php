<?php

namespace Slakbal\TenancyFoundation\Tests;

abstract class DomainModeTestCase extends TestCase
{
    // Set before the providers register, like an app's config files.
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('tenancy-foundation.resolution', 'domain');
        $app['config']->set('tenancy-foundation.domain', 'tenant.test');
        $app['config']->set('session.domain', '.example.com');
    }
}
