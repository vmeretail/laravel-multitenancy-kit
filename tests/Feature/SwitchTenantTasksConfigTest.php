<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Spatie\Multitenancy\Tasks\PrefixCacheTask;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;
use VmeRetail\MultitenancyKit\MultitenancyKitServiceProvider;
use VmeRetail\MultitenancyKit\Tasks\SwitchStoragePrefixTask;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class SwitchTenantTasksConfigTest extends TestCase
{
    public function test_default_switch_tenant_tasks_are_exposed_on_kit_config(): void
    {
        $this->assertSame([
            PrefixCacheTask::class,
            SwitchTenantDatabaseTask::class,
            SwitchStoragePrefixTask::class,
        ], config('multitenancy-kit.switch_tenant_tasks'));
    }

    public function test_spatie_database_connections_are_loaded_from_kit_config(): void
    {
        config([
            'multitenancy-kit.tenant_database_connection_name' => 'tenant',
            'multitenancy-kit.landlord_database_connection_name' => 'landlord',
        ]);

        app()->getProvider(MultitenancyKitServiceProvider::class)->packageRegistered();

        $this->assertSame('tenant', config('multitenancy.tenant_database_connection_name'));
        $this->assertSame('landlord', config('multitenancy.landlord_database_connection_name'));
    }

    public function test_spatie_switch_tenant_tasks_are_loaded_from_kit_config(): void
    {
        config([
            'multitenancy-kit.switch_tenant_tasks' => [
                PrefixCacheTask::class,
                SwitchStoragePrefixTask::class,
            ],
        ]);

        app()->getProvider(MultitenancyKitServiceProvider::class)->packageRegistered();

        $this->assertSame([
            PrefixCacheTask::class,
            SwitchStoragePrefixTask::class,
        ], config('multitenancy.switch_tenant_tasks'));
    }
}
