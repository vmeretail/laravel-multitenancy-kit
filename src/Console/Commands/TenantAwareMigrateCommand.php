<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Spatie\Multitenancy\Contracts\IsTenant;

final class TenantAwareMigrateCommand extends MigrateCommand
{
    protected function runMigrations(): void
    {
        if (! $this->option('database')) {
            // Migration paths do not change the connection; the app default is the tenant connection.
            $this->input->setOption('database', $this->databaseConnectionForCurrentContext());
        }

        parent::runMigrations();
    }

    /**
     * @return list<string>
     */
    protected function getMigrationPaths(): array
    {
        if ($this->input->hasOption('path') && $this->option('path')) {
            return parent::getMigrationPaths();
        }

        if (resolve(IsTenant::class)::checkCurrent()) {
            $paths = array_merge(
                $this->migrator->paths(),
                [$this->getMigrationPath()],
            );

            if ($this->option('pretend')) {
                $excluded = config('multitenancy-kit.excluded_pretend_migration_paths', []);

                $paths = array_values(array_filter(
                    $paths,
                    fn (string $path): bool => ! in_array($path, $excluded),
                ));
            }

            return $paths;
        }

        return array_values(array_filter([
            dirname(__DIR__, 3).'/database/migrations/landlord',
            $this->laravel->databasePath('migrations/landlord'),
        ], is_dir(...)));
    }

    private function databaseConnectionForCurrentContext(): string
    {
        if (resolve(IsTenant::class)::checkCurrent()) {
            return (string) config('multitenancy-kit.tenant_database_connection_name');
        }

        return (string) config('multitenancy-kit.landlord_database_connection_name');
    }
}
