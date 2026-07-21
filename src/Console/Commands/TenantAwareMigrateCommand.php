<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Spatie\Multitenancy\Contracts\IsTenant;

final class TenantAwareMigrateCommand extends MigrateCommand
{
    /**
     * @return list<string>
     */
    protected function getMigrationPaths(): array
    {
        if ($this->input->hasOption('path') && $this->option('path')) {
            return parent::getMigrationPaths();
        }

        if (app(IsTenant::class)::checkCurrent()) {
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
        ], fn (string $path): bool => is_dir($path)));
    }
}
