<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Seeder;
use Spatie\Multitenancy\Contracts\IsTenant;

final class TenantAwareSeedCommand extends SeedCommand
{
    protected function getSeeder(): Seeder
    {
        $class = $this->input->getArgument('class') ?? $this->input->getOption('class');

        if (! str_contains($class, '\\')) {
            $class = 'Database\\Seeders\\'.$class;
        }

        if ($class === 'Database\\Seeders\\DatabaseSeeder' && ! app(IsTenant::class)::checkCurrent()) {
            $landlordSeeder = config('multitenancy-kit.landlord_seeder');

            if ($landlordSeeder) {
                $class = $landlordSeeder;
            }
        }

        if ($class === 'Database\\Seeders\\DatabaseSeeder' && ! class_exists($class)) {
            $class = 'DatabaseSeeder';
        }

        return $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);
    }
}
