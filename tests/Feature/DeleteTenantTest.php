<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use VmeRetail\MultitenancyKit\Actions\DeleteTenant;
use VmeRetail\MultitenancyKit\Events\TenantCreated;
use VmeRetail\MultitenancyKit\Events\TenantDeleted;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class DeleteTenantTest extends TestCase
{
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TenantCreated::class]);

        $this->connection = config('multitenancy-kit.landlord_database_connection_name');

        Schema::connection($this->connection)->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_deletes_the_tenant_record(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.localhost',
        ]);

        app(DeleteTenant::class)->execute($tenant, dropDatabase: false);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id], $this->connection);
    }

    public function test_it_dispatches_tenant_deleted_event_with_database_dropped_false(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.localhost',
        ]);

        app(DeleteTenant::class)->execute($tenant, dropDatabase: false);

        Event::assertDispatched(TenantDeleted::class, function (TenantDeleted $event): bool {
            return $event->databaseDropped === false;
        });
    }

    public function test_it_dispatches_tenant_deleted_event_with_database_dropped_true(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.localhost',
        ]);

        // Set a valid database name for the drop operation (the auto-generated
        // name uses :memory: which fails the provisioner's validation regex)
        $tenant->updateQuietly(['database' => 'test_tenant_db']);

        app(DeleteTenant::class)->execute($tenant, dropDatabase: true);

        Event::assertDispatched(TenantDeleted::class, function (TenantDeleted $event): bool {
            return $event->databaseDropped === true;
        });
    }

    public function test_it_deletes_the_record_when_also_dropping_database(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.localhost',
        ]);

        $tenant->updateQuietly(['database' => 'test_tenant_db']);

        app(DeleteTenant::class)->execute($tenant, dropDatabase: true);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id], $this->connection);
    }

    public function test_event_contains_the_tenant(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.localhost',
        ]);

        $tenantId = $tenant->id;

        app(DeleteTenant::class)->execute($tenant, dropDatabase: false);

        Event::assertDispatched(TenantDeleted::class, function (TenantDeleted $event) use ($tenantId): bool {
            return $event->tenant->id === $tenantId;
        });
    }
}
