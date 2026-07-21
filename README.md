# Laravel Multitenancy Kit

A reusable multi-tenancy foundation for Laravel, built on top of [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy). Provides auto-provisioning, user sync, impersonation, tenant-aware migrations, and a landlord admin panel powered by Filament.

## Requirements

- PHP 8.2+
- Laravel 12+
- Filament 5+
- spatie/laravel-multitenancy 4+

## Installation

Require the package (via path repository or Composer):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravel-multitenancy-kit"
        }
    ],
    "require": {
        "vmeretail/laravel-multitenancy-kit": "*"
    }
}
```

The service providers are auto-discovered:

- `MultitenancyKitServiceProvider` — registers config, commands, routes, and the tenant-aware migrate command.
- `LandlordPanelProvider` — registers the Filament landlord admin panel.

### Publish the config

```bash
php artisan vendor:publish --tag=multitenancy-kit-config
```

This publishes `config/multitenancy-kit.php`.

## Documentation

- [Tenant scheduling](docs/tenant-scheduling.md) — define tenant-local scheduled Actions, queues, run tracking, and Horizon setup.

## Configuration

Key options in `config/multitenancy-kit.php`:

| Key | Description | Default |
|---|---|---|
| `central_domain` | Domain for the landlord panel | `localhost` |
| `tenant_model` | Tenant Eloquent model | `Tenant::class` |
| `central_user_model` | Central (landlord) user model | `CentralUser::class` |
| `tenant_user_model` | Tenant-side user model | `null` |
| `tenant_auth_guard` | Auth guard for tenant users | `tenant` |
| `landlord_panel_id` | Filament panel ID for landlord | `landlord` |
| `landlord_panel_path` | URL path for the landlord panel | `/admin` |
| `sync_fields` | Fields synced from central user to tenants | `['name', 'email', 'password']` |
| `auto_provision_database` | Auto-create DB on tenant creation | `true` |
| `tenant_seeder` | Seeder class to run after tenant migration | `null` |
| `tenant_migrations_path` | Path for tenant-specific migrations | `database/migrations/tenant` |
| `landlord_database_connection_name` | Name of the landlord DB connection | `landlord` |
| `switch_tenant_tasks` | Ordered Spatie switch tenant tasks | Prefix cache, switch database, switch storage prefix |

## Database & Migrations

The package uses a **split-database** architecture: landlord tables live in a central database, and each tenant gets its own database.

### Migration layout

```
database/
  migrations/            ← tenant migrations (standard Laravel path)
packages/
  laravel-multitenancy-kit/
    database/
      migrations/
        landlord/        ← landlord migrations (users, tenants, impersonation_tokens)
```

The package replaces Laravel's `migrate` command with `TenantAwareMigrateCommand`, which automatically routes to the correct migration path based on context:

- **No tenant current** → runs landlord migrations only (from the package's `database/migrations/landlord/`)
- **Tenant current** → runs tenant migrations only (from `database/migrations/`)

### Running landlord migrations

When no tenant is set as current, `migrate` runs the landlord migrations:

```bash
php artisan migrate
```

This creates the `users`, `tenants`, and `tenant_user_impersonation_tokens` tables on the landlord connection.

### Running tenant migrations

Tenant migrations run automatically when a new tenant is created (if `auto_provision_database` is `true`). The provisioning flow:

1. `CreateTenantDatabase` — creates the tenant's database
2. `RunTenantMigrations` — runs `php artisan migrate` inside the tenant's context
3. `RunTenantSeeders` — runs the configured `tenant_seeder` (if set)

### Running migrations for all tenants

Use the `tenants:artisan` command (provided by `spatie/laravel-multitenancy`) to run any artisan command for every tenant:

```bash
php artisan tenants:artisan migrate
```

To target a specific tenant by ID:

```bash
php artisan tenants:artisan migrate --tenant=1
```

The `tenants:artisan` command works with any artisan command, not just `migrate`. For example:

```bash
php artisan tenants:artisan db:seed --tenant=1
```

### Events

The package dispatches a `TenantCreated` event after a tenant is created. You can listen to this event to run custom logic:

```php
use VmeRetail\MultitenancyKit\Events\TenantCreated;

Event::listen(TenantCreated::class, function (TenantCreated $event) {
    // $event->tenant contains the newly created tenant
});
```

The built-in `ProvisionTenantListener` handles database provisioning and user sync automatically. Because provisioning is driven by an event (not by the model's `booted()` method), it works regardless of which tenant model class is configured via `tenant_model`.

### Running tenant migrations programmatically

You can also run migrations in code via the `RunTenantMigrations` action:

```php
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Actions\RunTenantMigrations;

// Single tenant
$tenant = Tenant::find(1);
app(RunTenantMigrations::class)->execute($tenant);

// All tenants
Tenant::all()->each(function (Tenant $tenant) {
    app(RunTenantMigrations::class)->execute($tenant);
});
```

### Using --path to target specific migrations

The `--path` flag bypasses the tenant-aware routing and passes through to the standard Laravel migrate command:

```bash
php artisan migrate --path=database/migrations/specific_file.php
```

## Creating a Central User

```bash
php artisan multitenancy-kit:make-user
```

Interactive prompts will ask for name, email, and password. Or pass options directly:

```bash
php artisan multitenancy-kit:make-user \
    --name="Admin" \
    --email="admin@example.com" \
    --password="secret123" \
    --admin
```

The `--admin` flag grants access to the landlord Filament panel.

## User Sync

When a `CentralUser` is created or updated, it is automatically synced to all tenants via `SyncCentralUserToTenantJob`. The sync:

1. Creates or updates the tenant-side user (matched by email)
2. Copies configured `sync_fields` from the central user
3. Marks the tenant user with `central_user = true`
4. Runs any configured `after_sync_pipeline` steps

### After-sync pipeline

Register pipeline steps to run additional logic after each user sync:

```php
// config/multitenancy-kit.php
'after_sync_pipeline' => [
    AssignDefaultRoleStep::class,
],
```

Each step must implement `AfterSyncStep`:

```php
use VmeRetail\MultitenancyKit\Contracts\AfterSyncStep;

final readonly class AssignDefaultRoleStep implements AfterSyncStep
{
    public function handle(array $data, Closure $next): mixed
    {
        $data['tenant_user']->assignRole('admin');

        return $next($data);
    }
}
```

## Impersonation

The package provides one-time impersonation tokens for logging into tenant panels as a specific user.

### Creating a token

```php
use VmeRetail\MultitenancyKit\Actions\CreateImpersonationToken;

$token = app(CreateImpersonationToken::class)->execute(
    tenant: $tenant,
    tenantUserId: $user->id,
    redirectUrl: '/dashboard',
);
```

### Routes

| Method | URI | Name |
|---|---|---|
| GET | `/impersonate/{token}` | `multitenancy-kit.impersonate` |
| POST | `/logout-impersonate` | `multitenancy-kit.logout-impersonate` |

The impersonate route must be accessed from the tenant's domain. It logs in as the tenant user and deletes the token. The logout route logs out and redirects back to the landlord panel.

### Generating impersonation URLs

Use the `tenant_route` helper to build a URL on the tenant's domain:

```php
$url = tenant_route($tenant->domain, 'multitenancy-kit.impersonate', [
    'token' => $token->token,
]);
```

## Landlord Panel

The package auto-registers a Filament panel at the configured `landlord_panel_path` on the `central_domain`. It includes resources for managing:

- **Central Users** — CRUD for landlord users
- **Tenants** — CRUD for tenant records

Access is controlled by the `CentralUser::canAccessPanel()` method, which checks the `is_admin` flag by default. Override this by setting `landlord_panel_access` to a class with a `canAccess(CentralUser): bool` method.

## Testing

### The `WithTenantDatabase` trait

The tenant-aware migrate command only runs tenant migrations when a tenant is current. In tests, no tenant is current by default, so `RefreshDatabase` would only create landlord tables.

The `WithTenantDatabase` trait solves this by binding a fake tenant to the container before `RefreshDatabase` runs:

```php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use VmeRetail\MultitenancyKit\Testing\WithTenantDatabase;

abstract class TestCase extends BaseTestCase
{
    use WithTenantDatabase;
}
```

This ensures `php artisan migrate` picks up the standard `database/migrations` path during test setup, creating all tenant tables in the test database.

The trait binds directly to the container (bypassing `makeCurrent()`) to avoid triggering `SwitchTenantDatabaseTask`, which would purge the in-memory test connection.
