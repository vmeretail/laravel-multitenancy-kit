<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DatabaseProvisioner
{
    public function createDatabase(string $name): void
    {
        $this->validateDatabaseName($name);

        $connection = config('multitenancy-kit.landlord_database_connection_name');
        $driver = config("database.connections.{$connection}.driver");

        match ($driver) {
            'mysql', 'mariadb' => $this->createMysqlDatabase($name, $connection),
            'pgsql' => $this->createPostgresDatabase($name, $connection),
            'sqlite' => $this->createSqliteDatabase($name),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    public function dropDatabase(string $name): void
    {
        $this->validateDatabaseName($name);

        $connection = config('multitenancy-kit.landlord_database_connection_name');
        $driver = config("database.connections.{$connection}.driver");

        match ($driver) {
            'mysql', 'mariadb' => DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$name}`"),
            'pgsql' => $this->dropPostgresDatabase($name, $connection),
            'sqlite' => $this->dropSqliteDatabase($name),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    private function createMysqlDatabase(string $name, string $connection): void
    {
        $charset = config("database.connections.{$connection}.charset", 'utf8mb4');
        $collation = config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');

        DB::connection($connection)->statement(
            "CREATE DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$collation}"
        );
    }

    private function createPostgresDatabase(string $name, string $connection): void
    {
        DB::connection($connection)->statement("CREATE DATABASE \"{$name}\"");
    }

    private function createSqliteDatabase(string $name): void
    {
        $path = database_path("{$name}.sqlite");

        if (! file_exists($path)) {
            touch($path);
        }
    }

    private function dropPostgresDatabase(string $name, string $connection): void
    {
        // Terminate active connections before dropping
        DB::connection($connection)->statement(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
            [$name]
        );

        DB::connection($connection)->statement("DROP DATABASE IF EXISTS \"{$name}\"");
    }

    private function dropSqliteDatabase(string $name): void
    {
        $path = database_path("{$name}.sqlite");

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function validateDatabaseName(string $name): void
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid database name: {$name}");
        }
    }
}
