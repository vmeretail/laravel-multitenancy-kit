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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'tenant');
        $app['config']->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('database.connections.landlord', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
