<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Contracts\ResolvesTenantTimezone;

final readonly class SystemTimezoneResolver implements ResolvesTenantTimezone
{
    public function timezoneFor(IsTenant $tenant): string
    {
        return config('app.timezone', 'UTC');
    }
}
