<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions;

use InvalidArgumentException;
use VmeRetail\MultitenancyKit\Contracts\RegistersTenantSchedules;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final readonly class RegisterConfiguredTenantSchedules
{
    public function __construct(
        private TenantScheduleRegistry $registry,
    ) {}

    public function execute(): void
    {
        $registrar = config('multitenancy-kit.tenant_schedule_registrar');

        if ($registrar === null) {
            return;
        }

        if (! is_subclass_of($registrar, RegistersTenantSchedules::class)) {
            throw new InvalidArgumentException("Tenant schedule registrar [{$registrar}] must implement ".RegistersTenantSchedules::class.'.');
        }

        app($registrar)->register($this->registry);
    }
}
