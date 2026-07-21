<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Contracts;

use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

interface RegistersTenantSchedules
{
    public function register(TenantScheduleRegistry $registry): void;
}
