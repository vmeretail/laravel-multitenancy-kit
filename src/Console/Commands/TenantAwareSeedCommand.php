<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Seeder;
use Spatie\Multitenancy\Contracts\IsTenant;

final class TenantAwareSeedCommand extends SeedCommand
{
    private const string DATABASE_SEEDER = 'Database\\Seeders\\DatabaseSeeder';

    protected function getDatabase(): string
    {
        $database = $this->input->getOption('database');

        if ($database) {
            return $database;
        }

        if (resolve(IsTenant::class)::checkCurrent()) {
            return (string) config('multitenancy-kit.tenant_database_connection_name');
        }

        return (string) config('multitenancy-kit.landlord_database_connection_name');
    }

    protected function getSeeder(): Seeder
    {
        $class = $this->input->getArgument('class') ?? $this->input->getOption('class');

        if (! str_contains($class, '\\')) {
            $class = 'Database\\Seeders\\'.$class;
        }

        if ($class === self::DATABASE_SEEDER && ! resolve(IsTenant::class)::checkCurrent()) {
            $landlordSeeder = config('multitenancy-kit.landlord_seeder');

            if ($landlordSeeder) {
                $class = $landlordSeeder;
            }
        }

        if ($class === self::DATABASE_SEEDER && ! class_exists($class)) {
            $class = 'DatabaseSeeder';
        }

        return $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);
    }
}
