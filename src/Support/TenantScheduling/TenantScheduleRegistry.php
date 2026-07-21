<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use InvalidArgumentException;
use VmeRetail\MultitenancyKit\Enums\CatchUpPolicy;

final class TenantScheduleRegistry
{
    /**
     * @var array<string, TenantScheduleDefinition>
     */
    private array $definitions = [];

    public function define(
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
        return $this->definitions[$id] = new TenantScheduleDefinition(
            id: $id,
            cadence: $cadence,
            handler: $handler,
            parameters: $parameters,
            appliesTo: $appliesTo,
            queue: $queue ?? config('multitenancy-kit.tenant_schedule_queue'),
            catchUpPolicy: $catchUpPolicy,
            maxAttempts: $maxAttempts,
            timeoutSeconds: $timeoutSeconds,
            staleGraceSeconds: $staleGraceSeconds,
        );
    }

    /**
     * @return array<string, TenantScheduleDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $id): TenantScheduleDefinition
    {
        return $this->definitions[$id] ?? throw new InvalidArgumentException("Tenant schedule [{$id}] is not registered.");
    }
}
