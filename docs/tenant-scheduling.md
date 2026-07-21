# Tenant Scheduling

Tenant scheduling runs application-owned Actions once per tenant using each tenant's local timezone. It is intended for low-frequency business tasks such as daily expiry, reconciliation, and cleanup workflows.

## Setup

Register the package scheduler from `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantSchedule;

TenantSchedule::schedule(Schedule::getFacadeRoot());
```

This registers two landlord scheduler commands:

- `tenant-schedule:dispatch`, every 15 minutes
- `tenant-schedule:sweep-stale`, hourly

The dispatcher checks configured tenant schedules for each tenant and only queues jobs that are due.

## Configuration

Configure the registrar and queue in `config/multitenancy-kit.php`:

```php
'tenant_schedule_registrar' => App\Scheduling\TenantSchedules::class,

'tenant_schedule_queue' => env('TENANT_SCHEDULE_QUEUE', 'default'),

'tenant_timezone_resolver' => App\Support\SettingsTenantTimezoneResolver::class,
```

`tenant_schedule_queue` is the default queue used when a schedule definition does not pass a queue. Use a queue watched by Horizon or your queue worker.

`tenant_timezone_resolver` should return the tenant's IANA timezone name. The package default returns `config('app.timezone', 'UTC')`.

## Defining Schedules

Create a registrar that implements `RegistersTenantSchedules`:

```php
namespace App\Scheduling;

use App\Actions\Coupons\ReactivateExhaustedCoupons;
use App\Actions\Promotions\ExpirePromotions;
use VmeRetail\MultitenancyKit\Contracts\RegistersTenantSchedules;
use VmeRetail\MultitenancyKit\Enums\CatchUpPolicy;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;

final readonly class TenantSchedules implements RegistersTenantSchedules
{
    public function register(TenantScheduleRegistry $registry): void
    {
        $registry->define(
            id: 'promotions.expire',
            cadence: Cadence::dailyAt('00:30'),
            handler: ExpirePromotions::class,
            catchUpPolicy: CatchUpPolicy::RunLatestOnly,
            maxAttempts: 3,
        );

        $registry->define(
            id: 'coupons.reactivate-exhausted',
            cadence: Cadence::daily(),
            handler: ReactivateExhaustedCoupons::class,
            catchUpPolicy: CatchUpPolicy::RunLatestOnly,
            maxAttempts: 3,
        );
    }
}
```

Handlers are resolved through Laravel's container and must expose an `execute()` method. If a handler needs parameters, pass named parameters:

```php
$registry->define(
    id: 'tiers.reconcile-period-points.weekly-full',
    cadence: Cadence::weeklyOn('sunday', '01:30'),
    handler: ReconcilePeriodPoints::class,
    parameters: ['full' => true],
);
```

## Cadences

Supported cadences:

```php
Cadence::daily();                  // 00:00 tenant-local time
Cadence::dailyAt('00:30');
Cadence::weekly();                 // Sunday 00:00 tenant-local time
Cadence::weeklyOn('sunday', '01:30');
Cadence::monthly();                // Day 1 at 00:00 tenant-local time
Cadence::monthlyOn(4, '15:00');
```

Cadences define both the due check and the period key used for duplicate prevention.

## Catch-Up Behavior

The dispatcher evaluates the current tenant-local period only. If the scheduler was not running yesterday, a daily schedule will not automatically enqueue yesterday's missed period today.

When a schedule is due for the current period, `catchUpPolicy` is stored with the definition for future policy-specific behavior, but the current dispatcher does not expand missed periods. Use `tenant-schedule:run --force --period=...` for manual backfills.

## Runtime Behavior

`tenant-schedule:dispatch` loops over tenants and registered definitions. For each pair, it:

1. checks the optional `appliesTo` Action,
2. resolves the tenant timezone,
3. checks whether the cadence is due in tenant-local time,
4. checks whether the current period already has a non-retryable run,
5. queues `RunTenantScheduledTask` only when work should run.

`RunTenantScheduledTask` is a landlord/orchestrator job marked `NotTenantAware`. It receives the tenant ID, makes that tenant current inside `handle()`, claims a row in `tenant_scheduled_runs`, runs the handler Action, and marks the run as succeeded or failed.

The job keeps its own due and claim checks as a safety guard for forced runs, manual runs, and jobs that were queued before a schedule changed.

## Duplicate Prevention and Retries

Runs are tracked in the landlord `tenant_scheduled_runs` table with a unique key on:

```text
tenant_id, schedule_id, period_key
```

A schedule is not queued again for a period that is already claimed, running, or succeeded. Failed and abandoned runs may be reclaimed until `maxAttempts` is reached.

The stale sweeper marks old claimed/running rows as abandoned based on each definition's `timeoutSeconds` and `staleGraceSeconds`.

## Manual Commands

Dispatch due schedules:

```bash
php artisan tenant-schedule:dispatch
```

Force one schedule for one tenant or all tenants:

```bash
php artisan tenant-schedule:run promotions.expire --force
php artisan tenant-schedule:run promotions.expire 1 --force
```

Override the period key when forcing a run:

```bash
php artisan tenant-schedule:run promotions.expire 1 --force --period=2026-05-08
```

Sweep stale runs:

```bash
php artisan tenant-schedule:sweep-stale
```

## Horizon

Tenant schedule jobs use the configured `tenant_schedule_queue`. Make sure Horizon watches that queue.

For the default configuration:

```php
'tenant_schedule_queue' => env('TENANT_SCHEDULE_QUEUE', 'default'),
```

Horizon's supervisor must include `default`:

```php
'queue' => ['default'],
```

If you set `TENANT_SCHEDULE_QUEUE=tenant-scheduled`, Horizon must watch that queue too.
