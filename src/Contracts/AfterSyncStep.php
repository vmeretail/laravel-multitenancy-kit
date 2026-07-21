<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Contracts;

use Closure;

interface AfterSyncStep
{
    /**
     * @param  array{central_user: mixed, tenant_user: mixed, tenant: mixed}  $data
     */
    public function handle(array $data, Closure $next): mixed;
}
