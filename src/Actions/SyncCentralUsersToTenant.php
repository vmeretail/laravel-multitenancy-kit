<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Jobs\SyncCentralUserToTenantJob;

final readonly class SyncCentralUsersToTenant
{
    public function execute(IsTenant $tenant): void
    {
        $centralUserModel = config('multitenancy-kit.central_user_model');

        foreach ($centralUserModel::all() as $centralUser) {
            SyncCentralUserToTenantJob::dispatch($centralUser, $tenant);
        }
    }
}
