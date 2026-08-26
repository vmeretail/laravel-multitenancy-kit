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
use VmeRetail\MultitenancyKit\Tests\Fixtures\TenantUser;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class SyncCentralUsersToTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'multitenancy.switch_tenant_tasks' => [],
            'multitenancy-kit.tenant_user_model' => TenantUser::class,
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

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('central_user')->default(false);
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

    public function test_it_creates_a_tenant_user_when_a_central_user_is_created(): void
    {
        Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]));

        CentralUser::create([
            'name' => 'Matt Wells',
            'email' => 'matt.wells@example.com',
            'password' => 'password',
        ]);

        $tenantUser = TenantUser::query()->sole();

        $this->assertSame('Matt Wells', $tenantUser->name);
        $this->assertSame('matt.wells@example.com', $tenantUser->email);
        $this->assertTrue((bool) $tenantUser->central_user);
    }

    public function test_it_updates_the_existing_tenant_user_when_a_central_users_email_changes(): void
    {
        Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]));

        $centralUser = CentralUser::withoutEvents(fn (): CentralUser => CentralUser::create([
            'name' => 'Matt Wells',
            'email' => 'matt.wells@example.com',
            'password' => 'password',
        ]));

        TenantUser::query()->create([
            'name' => $centralUser->name,
            'email' => $centralUser->email,
            'password' => $centralUser->password,
            'central_user' => true,
        ]);

        $centralUser->update(['email' => 'matthew.wells@example.com']);

        $this->assertSame(1, TenantUser::query()->count());
        $this->assertTrue(TenantUser::query()->where('email', 'matthew.wells@example.com')->exists());
        $this->assertFalse(TenantUser::query()->where('email', 'matt.wells@example.com')->exists());
    }

    public function test_it_uses_the_current_email_when_the_previous_tenant_user_does_not_exist(): void
    {
        Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => ':memory:',
        ]));

        $centralUser = CentralUser::withoutEvents(fn (): CentralUser => CentralUser::create([
            'name' => 'Matt Wells',
            'email' => 'matt.wells@example.com',
            'password' => 'password',
        ]));

        TenantUser::query()->create([
            'name' => $centralUser->name,
            'email' => 'matthew.wells@example.com',
            'password' => $centralUser->password,
            'central_user' => true,
        ]);

        $centralUser->update(['email' => 'matthew.wells@example.com']);

        $this->assertSame(1, TenantUser::query()->count());
        $this->assertTrue(TenantUser::query()->where('email', 'matthew.wells@example.com')->exists());
    }
}
