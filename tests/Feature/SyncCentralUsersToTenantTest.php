<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use VmeRetail\MultitenancyKit\Actions\SyncCentralUsersToTenant;
use VmeRetail\MultitenancyKit\Jobs\SyncCentralUserToTenantJob;
use VmeRetail\MultitenancyKit\Models\CentralUser;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class SyncCentralUsersToTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'multitenancy.switch_tenant_tasks' => [],
        ]);

        $connection = config('multitenancy-kit.landlord_database_connection_name');

        Schema::connection($connection)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection($connection)->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_dispatches_a_sync_job_for_each_central_user(): void
    {
        Bus::fake();

        CentralUser::withoutEvents(function (): void {
            CentralUser::create([
                'name' => 'User A',
                'email' => 'a@example.com',
                'password' => 'password',
            ]);

            CentralUser::create([
                'name' => 'User B',
                'email' => 'b@example.com',
                'password' => 'password',
            ]);
        });

        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]);

        app(SyncCentralUsersToTenant::class)->execute($tenant);

        Bus::assertDispatched(SyncCentralUserToTenantJob::class, 2);
    }

    public function test_it_dispatches_no_jobs_when_no_central_users_exist(): void
    {
        Bus::fake();

        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]);

        app(SyncCentralUsersToTenant::class)->execute($tenant);

        Bus::assertNotDispatched(SyncCentralUserToTenantJob::class);
    }
}
