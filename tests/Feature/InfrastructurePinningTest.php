<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use VmeRetail\MultitenancyKit\MultitenancyKitServiceProvider;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class InfrastructurePinningTest extends TestCase
{
    private string $landlordConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
    }

    public function test_session_is_pinned_to_landlord_when_driver_is_database(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('session.connection'));
    }

    public function test_landlord_connection_must_be_configured_explicitly(): void
    {
        config([
            'database.default' => 'tenant',
            'database.connections.landlord' => null,
        ]);

        $this->reRegisterProvider();

        $this->assertNull(config('database.connections.landlord'));
    }

    public function test_session_is_not_pinned_when_driver_is_not_database(): void
    {
        config(['session.driver' => 'array', 'session.connection' => null]);
        $this->reRegisterProvider();

        $this->assertNull(config('session.connection'));
    }

    public function test_cache_store_connection_is_pinned_to_landlord_when_driver_is_database(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('cache.stores.database.connection'));
    }

    public function test_cache_store_lock_connection_is_pinned_to_landlord_when_driver_is_database(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('cache.stores.database.lock_connection'));
    }

    public function test_cache_is_not_pinned_when_driver_is_not_database(): void
    {
        config([
            'cache.stores.database' => [
                'driver' => 'array',
                'connection' => null,
                'lock_connection' => null,
            ],
        ]);
        $this->reRegisterProvider();

        $this->assertNull(config('cache.stores.database.connection'));
        $this->assertNull(config('cache.stores.database.lock_connection'));
    }

    public function test_queue_connection_is_pinned_to_landlord_when_driver_is_database(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('queue.connections.database.connection'));
    }

    public function test_queue_connection_is_not_pinned_when_driver_is_not_database(): void
    {
        config([
            'queue.default' => 'sync',
            'queue.connections.sync' => ['driver' => 'sync', 'connection' => null],
        ]);
        $this->reRegisterProvider();

        $this->assertNull(config('queue.connections.sync.connection'));
    }

    public function test_queue_batching_is_pinned_to_landlord(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('queue.batching.database'));
    }

    public function test_queue_failed_is_pinned_to_landlord(): void
    {
        $this->setDatabaseInfrastructureConfig();
        $this->reRegisterProvider();

        $this->assertSame($this->landlordConnection, config('queue.failed.database'));
    }

    public function test_queue_failed_is_not_pinned_when_driver_is_null(): void
    {
        config([
            'queue.failed' => ['driver' => 'null', 'database' => null],
        ]);
        $this->reRegisterProvider();

        $this->assertNull(config('queue.failed.database'));
    }

    public function test_queue_batching_and_failed_are_still_pinned_when_driver_is_redis(): void
    {
        config([
            'queue.default' => 'redis',
            'queue.connections.redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'queue.batching' => [
                'database' => null,
                'table' => 'job_batches',
            ],
            'queue.failed' => [
                'driver' => 'database-uuids',
                'database' => null,
                'table' => 'failed_jobs',
            ],
        ]);

        $this->reRegisterProvider();

        $this->assertSame(
            'default',
            config('queue.connections.redis.connection'),
            'Redis queue connection must not be rewritten to a database connection.',
        );
        $this->assertSame($this->landlordConnection, config('queue.batching.database'));
        $this->assertSame($this->landlordConnection, config('queue.failed.database'));
    }

    private function setDatabaseInfrastructureConfig(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'cache.stores.database' => [
                'driver' => 'database',
                'connection' => null,
                'table' => 'cache',
                'lock_connection' => null,
                'lock_table' => 'cache_locks',
            ],
            'queue.default' => 'database',
            'queue.connections.database' => [
                'driver' => 'database',
                'connection' => null,
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'queue.batching' => [
                'database' => null,
                'table' => 'job_batches',
            ],
            'queue.failed' => [
                'driver' => 'database-uuids',
                'database' => null,
                'table' => 'failed_jobs',
            ],
        ]);
    }

    private function reRegisterProvider(): void
    {
        app()->getProvider(MultitenancyKitServiceProvider::class)->packageRegistered();
    }
}
