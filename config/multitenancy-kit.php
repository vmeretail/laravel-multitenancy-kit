<?php

declare(strict_types=1);

use Spatie\Multitenancy\Tasks\PrefixCacheTask;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;
use VmeRetail\MultitenancyKit\Database\Seeders\LandlordSeeder;
use VmeRetail\MultitenancyKit\Models\CentralUser;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\SystemTimezoneResolver;
use VmeRetail\MultitenancyKit\Tasks\SwitchStoragePrefixTask;

return [

    /*
    |--------------------------------------------------------------------------
    | Central Domain
    |--------------------------------------------------------------------------
    |
    | The domain used for the landlord (central) admin panel.
    |
    */

    'central_domain' => env('MULTITENANCY_KIT_CENTRAL_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */

    'tenant_model' => Tenant::class,

    'central_user_model' => CentralUser::class,

    // The tenant-side User model. App must set this.
    'tenant_user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    'tenant_auth_guard' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | Landlord Panel
    |--------------------------------------------------------------------------
    */

    'landlord_panel_id' => 'landlord',

    'landlord_panel_path' => '/admin',

    // Optional class to restrict panel access. Must have `canAccess(CentralUser): bool`.
    'landlord_panel_access' => null,

    /*
    |--------------------------------------------------------------------------
    | Tenant Panel
    |--------------------------------------------------------------------------
    |
    | Used for impersonation redirect (to build the tenant panel URL).
    |
    */

    'tenant_panel_id' => null,

    /*
    |--------------------------------------------------------------------------
    | User Sync
    |--------------------------------------------------------------------------
    */

    'sync_fields' => ['name', 'email', 'password'],

    // Array of AfterSyncStep classes run after each user sync.
    'after_sync_pipeline' => [],

    'sync_users_on_tenant_creation' => true,

    /*
    |--------------------------------------------------------------------------
    | Tenant Provisioning
    |--------------------------------------------------------------------------
    */

    'auto_provision_database' => true,

    'landlord_seeder' => LandlordSeeder::class,

    'tenant_seeder' => null,

    'tenant_migrations_path' => 'database/migrations/tenant',

    // Implementation of ResolvesTenantTimezone. Defaults to app.timezone.
    'tenant_timezone_resolver' => SystemTimezoneResolver::class,

    // Optional implementation of RegistersTenantSchedules for app-owned schedule definitions.
    'tenant_schedule_registrar' => null,

    // Queue used by app-owned tenant schedule jobs.
    'tenant_schedule_queue' => env('TENANT_SCHEDULE_QUEUE', 'default'),

    // Migration paths to exclude when running with --pretend. Useful for
    // data-only migrations (e.g. spatie/laravel-settings) that crash in
    // pretend mode because INSERT queries are never actually executed.
    'excluded_pretend_migration_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Define both connections explicitly in config/database.php. Spatie's
    | SwitchTenantDatabaseTask rewrites the tenant connection's `database`
    | at runtime, while landlord models always use the central connection.
    |
    */

    'tenant_database_connection_name' => 'tenant',

    'landlord_database_connection_name' => 'landlord',

    /*
    |--------------------------------------------------------------------------
    | Switch Tenant Tasks
    |--------------------------------------------------------------------------
    |
    | These tasks are passed through to Spatie's `multitenancy.switch_tenant_tasks`
    | config key and run, in order, whenever the current tenant changes.
    |
    */

    'switch_tenant_tasks' => [
        PrefixCacheTask::class,
        SwitchTenantDatabaseTask::class,
        SwitchStoragePrefixTask::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    */

    // Named route to redirect to after impersonating into a tenant.
    'tenant_dashboard_route' => null,

    // URL to redirect to after exiting impersonation (back to landlord panel).
    'exit_impersonation_redirect' => null,

    // Automatically show "Return to Admin" in tenant panel user menu for impersonated users.
    'show_exit_impersonation_menu_item' => true,

];
