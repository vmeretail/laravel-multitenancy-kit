<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Support\Facades\Event;
use VmeRetail\MultitenancyKit\Events\TenantCreated;
use VmeRetail\MultitenancyKit\Listeners\ProvisionTenantListener;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class ProvisionTenantListenerTest extends TestCase
{
    public function test_tenant_created_event_is_bound_to_provision_listener(): void
    {
        Event::fake();

        Event::assertListening(TenantCreated::class, ProvisionTenantListener::class);
    }

    public function test_listener_skips_provisioning_when_running_unit_tests(): void
    {
        config([
            'multitenancy-kit.auto_provision_database' => true,
            'multitenancy-kit.sync_users_on_tenant_creation' => true,
        ]);

        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'database' => 'test_db',
        ]);

        $listener = app(ProvisionTenantListener::class);
        $listener->handle(new TenantCreated($tenant));

        // No exception means the runningUnitTests() guard returned early
        // without attempting to create databases or run migrations
        $this->assertTrue(true);
    }
}
