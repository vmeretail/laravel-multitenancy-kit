<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use VmeRetail\MultitenancyKit\Models\CentralUser;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class MakeCentralUserCommandTest extends TestCase
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

    public function test_it_creates_a_central_user(): void
    {
        $this->artisan('multitenancy-kit:make-user', [
            '--name' => 'John Doe',
            '--email' => 'john@example.com',
            '--password' => 'password123',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = CentralUser::where('email', 'john@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('John Doe', $user->name);
        $this->assertFalse($user->is_admin);
    }

    public function test_it_creates_an_admin_user_with_admin_flag(): void
    {
        $this->artisan('multitenancy-kit:make-user', [
            '--name' => 'Admin User',
            '--email' => 'admin@example.com',
            '--password' => 'password123',
            '--admin' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = CentralUser::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
    }

    public function test_it_creates_a_non_admin_user_by_default(): void
    {
        $this->artisan('multitenancy-kit:make-user', [
            '--name' => 'Regular User',
            '--email' => 'regular@example.com',
            '--password' => 'password123',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = CentralUser::where('email', 'regular@example.com')->first();

        $this->assertNotNull($user);
        $this->assertFalse($user->is_admin);
    }

    public function test_it_hashes_the_password(): void
    {
        $this->artisan('multitenancy-kit:make-user', [
            '--name' => 'Test User',
            '--email' => 'test@example.com',
            '--password' => 'password123',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = CentralUser::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('password123', $user->getRawOriginal('password'));
        $this->assertTrue(password_verify('password123', $user->getRawOriginal('password')));
    }
}
