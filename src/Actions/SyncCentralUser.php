<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use VmeRetail\MultitenancyKit\Jobs\SyncCentralUserToTenantJob;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final readonly class SyncCentralUser
{
    public function execute(CentralUser $centralUser): void
    {
        $tenantModel = config('multitenancy-kit.tenant_model');

        foreach ($tenantModel::all() as $tenant) {
            SyncCentralUserToTenantJob::dispatch($centralUser, $tenant);
        }
    }
}
