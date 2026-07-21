<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Multitenancy\TenantFinder\DomainTenantFinder;
use VmeRetail\MultitenancyKit\Console\Commands\DispatchTenantSchedulesCommand;
use VmeRetail\MultitenancyKit\Console\Commands\MakeCentralUserCommand;
use VmeRetail\MultitenancyKit\Console\Commands\RunTenantScheduleCommand;
use VmeRetail\MultitenancyKit\Console\Commands\SweepStaleTenantScheduledRunsCommand;
use VmeRetail\MultitenancyKit\Console\Commands\TenantAwareMigrateCommand;
use VmeRetail\MultitenancyKit\Console\Commands\TenantAwareSeedCommand;
use VmeRetail\MultitenancyKit\Contracts\ResolvesTenantTimezone;
use VmeRetail\MultitenancyKit\Events\TenantCreated;
use VmeRetail\MultitenancyKit\Listeners\ProvisionTenantListener;
use VmeRetail\MultitenancyKit\Observers\CentralUserObserver;
use VmeRetail\MultitenancyKit\Observers\TenantObserver;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\RegisterConfiguredTenantSchedules;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\SystemTimezoneResolver;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final class MultitenancyKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('multitenancy-kit')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasCommand(MakeCentralUserCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->createLandlordConnection();
        $this->mergeAuthConfig();
        $this->mergeSpatieConfig();
        $this->pinSessionToLandlord();
        $this->pinCacheToLandlord();
        $this->pinQueueToLandlord();

        $this->app->singleton(TenantScheduleRegistry::class);
        $this->app->bind(ResolvesTenantTimezone::class, config('multitenancy-kit.tenant_timezone_resolver', SystemTimezoneResolver::class));
    }

    public function packageBooted(): void
    {
        $centralUserModel = config('multitenancy-kit.central_user_model');
        $centralUserModel::observe(CentralUserObserver::class);

        $tenantModel = config('multitenancy-kit.tenant_model');
        $tenantModel::observe(TenantObserver::class);

        Event::listen(TenantCreated::class, ProvisionTenantListener::class);

        $this->app->extend(MigrateCommand::class, fn ($command, $app) => new TenantAwareMigrateCommand($app[Migrator::class], $app[Dispatcher::class]));
        $this->app->extend(SeedCommand::class, fn ($command, $app) => new TenantAwareSeedCommand($app[ConnectionResolverInterface::class]));

        $this->app->make(RegisterConfiguredTenantSchedules::class)->execute();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchTenantSchedulesCommand::class,
                RunTenantScheduleCommand::class,
                SweepStaleTenantScheduledRunsCommand::class,
            ]);
        }

        $this->registerExitImpersonationMenuItem();
    }

    private function mergeAuthConfig(): void
    {
        $tenantGuard = config('multitenancy-kit.tenant_auth_guard');
        $tenantUserModel = config('multitenancy-kit.tenant_user_model');
        $centralUserModel = config('multitenancy-kit.central_user_model');

        // Set the default auth model to CentralUser (landlord context)
        config([
            'auth.providers.users.model' => $centralUserModel,
        ]);

        // Add tenant auth provider and guard
        if ($tenantUserModel) {
            config([
                "auth.providers.{$tenantGuard}" => [
                    'driver' => 'eloquent',
                    'model' => $tenantUserModel,
                ],
                "auth.guards.{$tenantGuard}" => [
                    'driver' => 'session',
                    'provider' => $tenantGuard,
                ],
            ]);
        }
    }

    private function createLandlordConnection(): void
    {
        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');

        if (config("database.connections.{$landlordConnection}")) {
            return;
        }

        $defaultConnection = config('database.default');
        $defaultConfig = config("database.connections.{$defaultConnection}");

        config([
            "database.connections.{$landlordConnection}" => $defaultConfig,
        ]);
    }

    /**
     * Pin the database session to the landlord connection.
     *
     * Spatie's SwitchTenantDatabaseTask rewrites the default connection's
     * database at runtime and sets it to null when forgetting a tenant.
     * The landlord connection is a stable snapshot that is never modified,
     * so sessions always reach the central database.
     */
    private function pinSessionToLandlord(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');

        config(['session.connection' => $landlordConnection]);
    }

    /**
     * Pin the database cache store to the landlord connection.
     *
     * Same rationale as pinSessionToLandlord — the default connection's
     * database is rewritten by SwitchTenantDatabaseTask, so the cache
     * store must use the stable landlord connection.
     */
    private function pinCacheToLandlord(): void
    {
        if (config('cache.stores.database.driver') !== 'database') {
            return;
        }

        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');

        config([
            'cache.stores.database.connection' => $landlordConnection,
            'cache.stores.database.lock_connection' => $landlordConnection,
        ]);
    }

    /**
     * Pin queue infrastructure to the landlord connection.
     *
     * The queue worker, batching table, and failed-jobs table all live
     * in the central database. Without explicit pinning they fall back
     * to the default connection which gets rewritten (or nulled) by
     * SwitchTenantDatabaseTask.
     */
    private function pinQueueToLandlord(): void
    {
        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
        $defaultQueue = config('queue.default');

        if (config("queue.connections.{$defaultQueue}.driver") === 'database') {
            config(["queue.connections.{$defaultQueue}.connection" => $landlordConnection]);
        }

        config(['queue.batching.database' => $landlordConnection]);

        if (config('queue.failed.driver') !== 'null') {
            config(['queue.failed.database' => $landlordConnection]);
        }
    }

    private function mergeSpatieConfig(): void
    {
        $tenantModel = config('multitenancy-kit.tenant_model');
        $landlordConnection = config('multitenancy-kit.landlord_database_connection_name');
        $switchTenantTasks = config('multitenancy-kit.switch_tenant_tasks', []);

        config([
            'multitenancy.tenant_model' => $tenantModel,
            'multitenancy.tenant_finder' => DomainTenantFinder::class,
            'multitenancy.landlord_database_connection_name' => $landlordConnection,
            'multitenancy.switch_tenant_tasks' => $switchTenantTasks,
        ]);
    }

    private function registerExitImpersonationMenuItem(): void
    {
        if (! $this->app->bound('filament')) {
            return;
        }

        Filament::serving(function (): void {
            $panel = Filament::getCurrentPanel();
            $landlordPanelId = config('multitenancy-kit.landlord_panel_id');

            if (! config('multitenancy-kit.show_exit_impersonation_menu_item', true)) {
                return;
            }

            if ($panel?->getId() === $landlordPanelId) {
                return;
            }

            $guard = config('multitenancy-kit.tenant_auth_guard');

            $panel?->userMenuItems([
                Action::make('exitImpersonation')
                    ->label(__('Return to Admin'))
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('gray')
                    ->url(fn (): string => route('multitenancy-kit.logout-impersonate'))
                    ->postToUrl()
                    ->sort(PHP_INT_MAX - 1)
                    ->visible(fn (): bool => (bool) auth($guard)->user()?->central_user),
            ]);
        });
    }
}
