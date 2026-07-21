<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions;

use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;
use VmeRetail\MultitenancyKit\Models\TenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final readonly class SweepStaleTenantScheduledRuns
{
    public function __construct(
        private TenantScheduleRegistry $registry,
    ) {}

    public function execute(): int
    {
        $abandoned = 0;

        foreach ($this->registry->all() as $definition) {
            $staleBefore = now()->subSeconds($definition->timeoutSeconds + $definition->staleGraceSeconds);

            $abandoned += TenantScheduledRun::query()
                ->where('schedule_id', $definition->id)
                ->whereIn('status', [TenantScheduledRunStatus::Claimed, TenantScheduledRunStatus::Running])
                ->where(function ($query) use ($staleBefore): void {
                    $query
                        ->where('started_at', '<', $staleBefore)
                        ->orWhere(function ($query) use ($staleBefore): void {
                            $query->whereNull('started_at')->where('claimed_at', '<', $staleBefore);
                        });
                })
                ->update([
                    'status' => TenantScheduledRunStatus::Abandoned,
                    'finished_at' => now(),
                ]);
        }

        return $abandoned;
    }
}
