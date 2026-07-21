<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Observers;

use VmeRetail\MultitenancyKit\Actions\SyncCentralUser;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final readonly class CentralUserObserver
{
    public function __construct(
        private SyncCentralUser $syncCentralUser,
    ) {}

    public function saved(CentralUser $centralUser): void
    {
        $this->syncCentralUser->execute($centralUser);
    }
}
