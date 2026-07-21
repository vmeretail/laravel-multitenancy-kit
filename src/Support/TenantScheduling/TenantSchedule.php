<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use Illuminate\Console\Scheduling\Schedule;
use VmeRetail\MultitenancyKit\Console\Commands\DispatchTenantSchedulesCommand;
use VmeRetail\MultitenancyKit\Console\Commands\SweepStaleTenantScheduledRunsCommand;
use VmeRetail\MultitenancyKit\Enums\CatchUpPolicy;

final class TenantSchedule
{
    public static function define(
        string $id,
        CadenceContract $cadence,
        string $handler,
        array $parameters = [],
        ?string $appliesTo = null,
        ?string $queue = null,
        CatchUpPolicy $catchUpPolicy = CatchUpPolicy::SkipMissed,
        int $maxAttempts = 1,
        int $timeoutSeconds = 3600,
        int $staleGraceSeconds = 300,
    ): TenantScheduleDefinition {
        return app(TenantScheduleRegistry::class)->define(
            id: $id,
            cadence: $cadence,
            handler: $handler,
            parameters: $parameters,
            appliesTo: $appliesTo,
            queue: $queue,
            catchUpPolicy: $catchUpPolicy,
            maxAttempts: $maxAttempts,
            timeoutSeconds: $timeoutSeconds,
            staleGraceSeconds: $staleGraceSeconds,
        );
    }

    public static function schedule(Schedule $schedule): void
    {
        $schedule->command(DispatchTenantSchedulesCommand::class)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command(SweepStaleTenantScheduledRunsCommand::class)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }
}
