<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Throwable;
use VmeRetail\MultitenancyKit\Console\Commands\TenantAwareSeedCommand;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class TenantAwareSeedCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('multitenancy-kit.landlord_database_connection_name');

        Schema::connection($connection)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
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

    public function test_seed_command_resolves_to_tenant_aware_seed_command(): void
    {
        $command = $this->app->make(SeedCommand::class);

        $this->assertInstanceOf(TenantAwareSeedCommand::class, $command);
    }

    public function test_it_runs_landlord_seeder_when_no_tenant_is_current(): void
    {
        Tenant::forgetCurrent();

        $this->artisan('db:seed', ['--no-interaction' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@vme.coop',
        ], config('multitenancy-kit.landlord_database_connection_name'));
    }

    public function test_it_respects_explicit_class_option(): void
    {
        Tenant::forgetCurrent();

        $seederClass = new class extends Seeder
        {
            public static bool $ran = false;

            public function run(): void
            {
                self::$ran = true;
            }
        };

        $className = $seederClass::class;
        $this->app->instance($className, $seederClass);

        $this->artisan('db:seed', [
            '--class' => $className,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertTrue($seederClass::$ran);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@vme.coop',
        ], config('multitenancy-kit.landlord_database_connection_name'));
    }

    public function test_it_falls_through_to_default_when_config_is_null(): void
    {
        Tenant::forgetCurrent();

        config(['multitenancy-kit.landlord_seeder' => null]);

        // When landlord_seeder config is null, the command falls through to
        // Database\Seeders\DatabaseSeeder which doesn't exist in package tests.
        // We catch the expected error and verify LandlordSeeder was NOT run.
        try {
            $this->artisan('db:seed', ['--no-interaction' => true]);
        } catch (Throwable) {
            // Expected — DatabaseSeeder class doesn't exist in package test context.
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@vme.coop',
        ], config('multitenancy-kit.landlord_database_connection_name'));
    }
}
