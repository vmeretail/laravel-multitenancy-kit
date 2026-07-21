<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use Carbon\CarbonImmutable;

interface CadenceContract
{
    public function isDue(CarbonImmutable $tenantNow): bool;

    public function periodKey(CarbonImmutable $tenantNow): string;

    public function targetDescription(CarbonImmutable $tenantNow): string;
}
