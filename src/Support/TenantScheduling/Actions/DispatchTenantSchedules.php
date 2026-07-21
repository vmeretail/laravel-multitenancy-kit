<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Contracts\ResolvesTenantTimezone;
use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;
use VmeRetail\MultitenancyKit\Jobs\RunTenantScheduledTask;
use VmeRetail\MultitenancyKit\Models\TenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleDefinition;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final readonly class DispatchTenantSchedules
{
    public function __construct(
        private TenantScheduleRegistry $registry,
        private ResolvesTenantTimezone $timezoneResolver,
    ) {}

    public function execute(): int
    {
        $dispatched = 0;
        $tenantModel = config('multitenancy-kit.tenant_model');

        /** @var Collection<int, object> $tenants */
        $tenants = $tenantModel::query()->get();

        foreach ($tenants as $tenant) {
            foreach ($this->registry->all() as $definition) {
                if ($definition->appliesTo !== null && ! app($definition->appliesTo)->execute($tenant)) {
                    continue;
                }

                if (! $this->shouldDispatch($tenant, $definition)) {
                    continue;
                }

                $job = new RunTenantScheduledTask(
                    tenantId: $tenant->getKey(),
                    scheduleId: $definition->id,
                );

                if ($definition->queue !== null) {
                    $job->onQueue($definition->queue);
                }

                dispatch($job);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    private function shouldDispatch(IsTenant $tenant, TenantScheduleDefinition $definition): bool
    {
        try {
            $timezone = $this->timezoneResolver->timezoneFor($tenant);
            $tenantNow = CarbonImmutable::now($timezone);

            if (! $definition->cadence->isDue($tenantNow)) {
                return false;
            }

            /** @var TenantScheduledRun|null $run */
            $run = TenantScheduledRun::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('schedule_id', $definition->id)
                ->where('period_key', $definition->cadence->periodKey($tenantNow))
                ->first();

            if ($run === null) {
                return true;
            }

            return in_array($run->status, [TenantScheduledRunStatus::Failed, TenantScheduledRunStatus::Abandoned], true)
                && $run->attempts < $definition->maxAttempts;
        } finally {
            $tenant::forgetCurrent();
        }
    }
}
