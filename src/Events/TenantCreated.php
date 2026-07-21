<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Events;

use Spatie\Multitenancy\Contracts\IsTenant;

final readonly class TenantCreated
{
    public function __construct(
        public IsTenant $tenant,
    ) {}
}
