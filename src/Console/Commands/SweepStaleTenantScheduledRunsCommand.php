<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Console\Command;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\SweepStaleTenantScheduledRuns;

final class SweepStaleTenantScheduledRunsCommand extends Command
{
    protected $signature = 'tenant-schedule:sweep-stale';

    protected $description = 'Mark stale tenant scheduled runs as abandoned so they can be retried.';

    public function handle(SweepStaleTenantScheduledRuns $sweepStaleTenantScheduledRuns): int
    {
        $abandoned = $sweepStaleTenantScheduledRuns->execute();

        $this->info("Abandoned {$abandoned} stale tenant scheduled run(s).");

        return self::SUCCESS;
    }
}
