<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use VmeRetail\MultitenancyKit\Actions\SyncCentralUserToTenant;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final class SyncCentralUserToTenantJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly CentralUser $centralUser,
        private readonly IsTenant $tenant,
    ) {}

    public function handle(SyncCentralUserToTenant $action): void
    {
        $action->execute($this->centralUser, $this->tenant);
    }
}
