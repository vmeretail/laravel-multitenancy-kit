<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Throwable;
use VmeRetail\MultitenancyKit\Events\TenantDeleted;

final readonly class DeleteTenant
{
    public function __construct(
        private DropTenantDatabase $dropTenantDatabase,
    ) {}

    /**
     * @throws Throwable
     */
    public function execute(IsTenant $tenant, bool $dropDatabase): void
    {
        DB::transaction(function () use ($tenant, $dropDatabase): void {
            if ($dropDatabase) {
                $this->dropTenantDatabase->execute($tenant);
            }

            $tenant->delete();
        });

        event(new TenantDeleted($tenant, $dropDatabase));
    }
}
