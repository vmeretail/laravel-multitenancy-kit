<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use VmeRetail\MultitenancyKit\Enums\CatchUpPolicy;

final readonly class TenantScheduleDefinition
{
    public function __construct(
        public string $id,
        public CadenceContract $cadence,
        public string $handler,
        /** @var array<string, mixed> */
        public array $parameters = [],
        public ?string $appliesTo = null,
        public ?string $queue = null,
        public CatchUpPolicy $catchUpPolicy = CatchUpPolicy::SkipMissed,
        public int $maxAttempts = 1,
        public int $timeoutSeconds = 3600,
        public int $staleGraceSeconds = 300,
    ) {}
}
