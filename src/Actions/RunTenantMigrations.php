<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Illuminate\Support\Facades\Artisan;
use Spatie\Multitenancy\Contracts\IsTenant;

final readonly class RunTenantMigrations
{
    public function execute(IsTenant $tenant): void
    {
        $tenant->execute(function (): void {
            Artisan::call('migrate', [
                '--database' => config('multitenancy-kit.tenant_database_connection_name'),
                '--force' => true,
            ]);
        });
    }
}
