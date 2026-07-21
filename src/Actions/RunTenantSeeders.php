<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Illuminate\Support\Facades\Artisan;
use Spatie\Multitenancy\Contracts\IsTenant;

final readonly class RunTenantSeeders
{
    public function execute(IsTenant $tenant): void
    {
        $seeder = config('multitenancy-kit.tenant_seeder');

        if (! $seeder) {
            return;
        }

        $tenant->execute(function () use ($seeder): void {
            Artisan::call('db:seed', [
                '--database' => config('database.default'),
                '--class' => $seeder,
                '--force' => true,
            ]);
        });
    }
}
