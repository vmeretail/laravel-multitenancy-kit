<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Console\Commands;

use Illuminate\Console\Command;
use VmeRetail\MultitenancyKit\Jobs\RunTenantScheduledTask;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final class RunTenantScheduleCommand extends Command
{
    protected $signature = 'tenant-schedule:run
        {scheduleId : The registered tenant schedule ID}
        {tenantId? : Optional tenant ID. Omit to run for all tenants}
        {--force : Run even when the schedule is not due}
        {--period= : Override the tenant-local period key}';

    protected $description = 'Run a registered tenant schedule for one tenant or all tenants.';

    public function handle(TenantScheduleRegistry $registry): int
    {
        $definition = $registry->get((string) $this->argument('scheduleId'));
        $tenantModel = config('multitenancy-kit.tenant_model');
        $tenantId = $this->argument('tenantId');

        $tenants = $tenantId !== null
            ? $tenantModel::query()->whereKey($tenantId)->get()
            : $tenantModel::query()->get();

        foreach ($tenants as $tenant) {
            $job = new RunTenantScheduledTask(
                tenantId: $tenant->getKey(),
                scheduleId: $definition->id,
                force: (bool) $this->option('force'),
                period: $this->option('period') !== null ? (string) $this->option('period') : null,
            );

            if ($definition->queue !== null) {
                $job->onQueue($definition->queue);
            }

            dispatch($job);
        }

        $this->info("Dispatched {$tenants->count()} tenant scheduled job(s).");

        return self::SUCCESS;
    }
}
