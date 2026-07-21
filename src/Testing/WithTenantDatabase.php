<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Testing;

trait WithTenantDatabase
{
    protected function setUpTraits(): array
    {
        $this->bootTenantForTesting();

        return parent::setUpTraits();
    }

    protected function bootTenantForTesting(): void
    {
        $tenantModel = config('multitenancy-kit.tenant_model');
        $containerKey = config('multitenancy.current_tenant_container_key', 'currentTenant');

        app()->instance($containerKey, new $tenantModel([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]));
    }
}
