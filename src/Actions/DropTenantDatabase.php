<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Support\DatabaseProvisioner;

final readonly class DropTenantDatabase
{
    public function __construct(
        private DatabaseProvisioner $provisioner,
    ) {}

    public function execute(IsTenant $tenant): void
    {
        $this->provisioner->dropDatabase($tenant->database);
    }
}
