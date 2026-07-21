<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use VmeRetail\MultitenancyKit\Events\TenantCreated;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class TenantObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('multitenancy-kit.landlord_database_connection_name');

        Schema::connection($connection)->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_dispatches_tenant_created_event_when_tenant_is_created(): void
    {
        Event::fake([TenantCreated::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-customer.localhost',
        ]);

        Event::assertDispatched(TenantCreated::class, function (TenantCreated $event) use ($tenant): bool {
            return $event->tenant->is($tenant);
        });
    }

    public function test_it_prefixes_database_name_with_landlord_database_on_creation(): void
    {
        Event::fake([TenantCreated::class]);

        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
        $landlordDatabase = config("database.connections.{$landlordConnection}.database");

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-customer.localhost',
        ]);

        $this->assertSame($landlordDatabase.'__test_tenant', $tenant->database);
    }

    public function test_it_overrides_manually_provided_database_name(): void
    {
        Event::fake([TenantCreated::class]);

        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
        $landlordDatabase = config("database.connections.{$landlordConnection}.database");

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-customer.localhost',
            'database' => 'should_be_overridden',
        ]);

        $this->assertSame($landlordDatabase.'__test_tenant', $tenant->database);
    }
}
