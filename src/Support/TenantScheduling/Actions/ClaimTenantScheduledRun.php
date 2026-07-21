<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Throwable;
use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;
use VmeRetail\MultitenancyKit\Models\TenantScheduledRun;

final readonly class ClaimTenantScheduledRun
{
    /**
     * @throws Throwable
     */
    public function execute(IsTenant $tenant, string $scheduleId, string $periodKey, int $maxAttempts): ?TenantScheduledRun
    {
        try {
            return TenantScheduledRun::query()->create([
                'tenant_id' => $tenant->getKey(),
                'schedule_id' => $scheduleId,
                'period_key' => $periodKey,
                'status' => TenantScheduledRunStatus::Claimed,
                'claimed_at' => now(),
                'attempts' => 1,
            ]);
        } catch (QueryException) {
            return $this->reclaimExistingRun($tenant, $scheduleId, $periodKey, $maxAttempts);
        }
    }

    /**
     * @throws Throwable
     */
    private function reclaimExistingRun(IsTenant $tenant, string $scheduleId, string $periodKey, int $maxAttempts): ?TenantScheduledRun
    {
        return DB::connection(config('multitenancy-kit.landlord_database_connection_name'))
            ->transaction(function () use ($tenant, $scheduleId, $periodKey, $maxAttempts): ?TenantScheduledRun {
                /** @var TenantScheduledRun|null $run */
                $run = TenantScheduledRun::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('schedule_id', $scheduleId)
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();

                if ($run === null) {
                    return null;
                }

                if (! in_array($run->status, [TenantScheduledRunStatus::Failed, TenantScheduledRunStatus::Abandoned], true)) {
                    return null;
                }

                if ($run->attempts >= $maxAttempts) {
                    return null;
                }

                $run->update([
                    'status' => TenantScheduledRunStatus::Claimed,
                    'claimed_at' => now(),
                    'started_at' => null,
                    'finished_at' => null,
                    'attempts' => $run->attempts + 1,
                ]);

                return $run->refresh();
            });
    }
}
