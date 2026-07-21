<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Console\Command;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\DispatchTenantSchedules;

final class DispatchTenantSchedulesCommand extends Command
{
    protected $signature = 'tenant-schedule:dispatch';

    protected $description = 'Dispatch tenant-local scheduled jobs that are due for each tenant.';

    public function handle(DispatchTenantSchedules $dispatchTenantSchedules): int
    {
        $dispatched = $dispatchTenantSchedules->execute();

        $this->info("Dispatched {$dispatched} tenant scheduled job(s).");

        return self::SUCCESS;
    }
}
