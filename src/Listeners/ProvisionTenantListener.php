<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Listeners;

use VmeRetail\MultitenancyKit\Actions\CreateTenantDatabase;
use VmeRetail\MultitenancyKit\Actions\RunTenantMigrations;
use VmeRetail\MultitenancyKit\Actions\RunTenantSeeders;
use VmeRetail\MultitenancyKit\Actions\SyncCentralUsersToTenant;
use VmeRetail\MultitenancyKit\Events\TenantCreated;

final readonly class ProvisionTenantListener
{
    public function __construct(
        private CreateTenantDatabase $createTenantDatabase,
        private RunTenantMigrations $runTenantMigrations,
        private RunTenantSeeders $runTenantSeeders,
        private SyncCentralUsersToTenant $syncCentralUsersToTenant,
    ) {}

    public function handle(TenantCreated $event): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (! config('multitenancy-kit.auto_provision_database')) {
            return;
        }

        $this->createTenantDatabase->execute($event->tenant);
        $this->runTenantMigrations->execute($event->tenant);
        $this->runTenantSeeders->execute($event->tenant);

        if (config('multitenancy-kit.sync_users_on_tenant_creation')) {
            $this->syncCentralUsersToTenant->execute($event->tenant);
        }
    }
}
