<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use VmeRetail\MultitenancyKit\MultitenancyKitServiceProvider;
use VmeRetail\MultitenancyKit\Testing\WithTenantDatabase;

abstract class TestCase extends OrchestraTestCase
{
    use WithTenantDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            MultitenancyServiceProvider::class,
            MultitenancyKitServiceProvider::class,
        ];
    }
}
