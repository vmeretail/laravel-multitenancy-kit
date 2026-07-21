<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Contracts;

use Spatie\Multitenancy\Contracts\IsTenant;

interface ResolvesTenantTimezone
{
    public function timezoneFor(IsTenant $tenant): string;
}
