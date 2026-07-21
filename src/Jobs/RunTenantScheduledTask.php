<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;
use VmeRetail\MultitenancyKit\Contracts\ResolvesTenantTimezone;
use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;
use VmeRetail\MultitenancyKit\Models\TenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\ClaimTenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final class RunTenantScheduledTask implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int|string $tenantId,
        public string $scheduleId,
        public bool $force = false,
        public ?string $period = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        TenantScheduleRegistry $registry,
        ClaimTenantScheduledRun $claimTenantScheduledRun,
        ResolvesTenantTimezone $timezoneResolver,
    ): void {
        $tenant = $this->tenant();
        $definition = $registry->get($this->scheduleId);

        $tenant->makeCurrent();

        try {
            $timezone = $timezoneResolver->timezoneFor($tenant);
            $tenantNow = CarbonImmutable::now($timezone);
            $periodKey = $this->period ?? $definition->cadence->periodKey($tenantNow);

            if (! $this->force && ! $definition->cadence->isDue($tenantNow)) {
                return;
            }

            $run = $claimTenantScheduledRun->execute(
                tenant: $tenant,
                scheduleId: $definition->id,
                periodKey: $periodKey,
                maxAttempts: $definition->maxAttempts,
            );

            if ($run === null) {
                return;
            }

            $run->update([
                'status' => TenantScheduledRunStatus::Running,
                'started_at' => now(),
                'metadata' => [
                    'tenant_timezone' => $timezone,
                    'target_local_time' => $definition->cadence->targetDescription($tenantNow),
                ],
            ]);

            app()->call([app($definition->handler), 'execute'], $definition->parameters);

            $run->update([
                'status' => TenantScheduledRunStatus::Succeeded,
                'finished_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            if (isset($run) && $run instanceof TenantScheduledRun) {
                $run->update([
                    'status' => TenantScheduledRunStatus::Failed,
                    'finished_at' => now(),
                    'last_error' => mb_substr($exception->getMessage(), 0, 65535),
                ]);
            }

            throw $exception;
        } finally {
            $tenant::forgetCurrent();
        }
    }

    private function tenant(): IsTenant
    {
        $tenantModel = config('multitenancy-kit.tenant_model');

        return $tenantModel::query()->findOrFail($this->tenantId);
    }
}
