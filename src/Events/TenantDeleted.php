<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Events;

use Spatie\Multitenancy\Contracts\IsTenant;

final readonly class TenantDeleted
{
    public function __construct(
        public IsTenant $tenant,
        public bool $databaseDropped,
    ) {}
}
