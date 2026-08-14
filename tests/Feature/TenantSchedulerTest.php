<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use VmeRetail\MultitenancyKit\Contracts\RegistersTenantSchedules;
use VmeRetail\MultitenancyKit\Contracts\ResolvesTenantTimezone;
use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;
use VmeRetail\MultitenancyKit\Jobs\RunTenantScheduledTask;
use VmeRetail\MultitenancyKit\Models\Tenant;
use VmeRetail\MultitenancyKit\Models\TenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\ClaimTenantScheduledRun;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\DispatchTenantSchedules;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\RegisterConfiguredTenantSchedules;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Actions\SweepStaleTenantScheduledRuns;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\DailyCadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\MonthlyCadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\WeeklyCadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantSchedule;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\TenantScheduleRegistry;
use VmeRetail\MultitenancyKit\Tests\TestCase;

final class TenantSchedulerTest extends TestCase
{
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = config('multitenancy-kit.landlord_database_connection_name');

        Schema::connection($this->connection)->create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('tenant_scheduled_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('schedule_id');
            $table->string('period_key');
            $table->string('status');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'schedule_id', 'period_key']);
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_cadences_compute_due_state_and_period_keys_in_tenant_local_time(): void
    {
        $daily = new DailyCadence('00:30');
        $weekly = new WeeklyCadence('monday', '01:30');
        $monthly = new MonthlyCadence(1, '02:00');

        $this->assertFalse($daily->isDue(CarbonImmutable::parse('2026-05-08 00:29:59', 'Europe/Malta')));
        $this->assertTrue($daily->isDue(CarbonImmutable::parse('2026-05-08 00:30:00', 'Europe/Malta')));
        $this->assertSame('2026-05-08', $daily->periodKey(CarbonImmutable::parse('2026-05-08 23:00:00', 'Europe/Malta')));

        $this->assertTrue($weekly->isDue(CarbonImmutable::parse('2024-12-30 01:30:00', 'Europe/Malta')));
        $this->assertSame('2025-W01', $weekly->periodKey(CarbonImmutable::parse('2024-12-30 12:00:00', 'Europe/Malta')));

        $this->assertTrue($monthly->isDue(CarbonImmutable::parse('2026-05-01 02:00:00', 'Europe/Malta')));
        $this->assertSame('2026-05', $monthly->periodKey(CarbonImmutable::parse('2026-05-31 23:59:59', 'Europe/Malta')));
    }

    public function test_cadences_can_be_created_with_scheduler_style_factories(): void
    {
        $this->assertInstanceOf(DailyCadence::class, Cadence::daily());
        $this->assertInstanceOf(DailyCadence::class, Cadence::dailyAt('00:30'));
        $this->assertInstanceOf(WeeklyCadence::class, Cadence::weekly());
        $this->assertInstanceOf(WeeklyCadence::class, Cadence::weeklyOn('monday', '08:00'));
        $this->assertInstanceOf(MonthlyCadence::class, Cadence::monthly());
        $this->assertInstanceOf(MonthlyCadence::class, Cadence::monthlyOn(4, '15:00'));
        $this->assertInstanceOf(DailyCadence::class, DailyCadence::dailyAt('00:30'));
        $this->assertInstanceOf(WeeklyCadence::class, WeeklyCadence::weeklyOn('monday', '08:00'));
        $this->assertInstanceOf(MonthlyCadence::class, MonthlyCadence::monthlyOn(4, '15:00'));
    }

    public function test_dispatcher_dispatches_registered_schedules_for_eligible_tenants(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-08 00:30:00', 'UTC'));

        $firstTenant = Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
            'settings' => ['enabled' => true],
        ]);

        $secondTenant = Tenant::query()->create([
            'name' => 'Second',
            'domain' => 'second.test',
            'database' => 'second',
            'settings' => ['enabled' => false],
        ]);

        TenantSchedule::define(
            id: 'test.daily',
            cadence: new DailyCadence('00:30'),
            handler: FakeTenantScheduleHandler::class,
            parameters: ['force' => true],
            appliesTo: EnabledTenantOnly::class,
        );

        app(DispatchTenantSchedules::class)->execute();

        Queue::assertPushed(RunTenantScheduledTask::class, fn (RunTenantScheduledTask $job): bool => $job->tenantId === $firstTenant->getKey()
            && $job->scheduleId === 'test.daily');

        Queue::assertNotPushed(RunTenantScheduledTask::class, fn (RunTenantScheduledTask $job): bool => $job->tenantId === $secondTenant->getKey());
    }

    public function test_dispatcher_does_not_dispatch_schedules_before_they_are_due(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-08 00:29:59', 'UTC'));

        Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
        ]);

        TenantSchedule::define(
            id: 'test.daily',
            cadence: new DailyCadence('00:30'),
            handler: FakeTenantScheduleHandler::class,
        );

        $dispatched = app(DispatchTenantSchedules::class)->execute();

        $this->assertSame(0, $dispatched);
        Queue::assertNothingPushed();
    }

    public function test_dispatcher_does_not_dispatch_schedules_that_already_ran_for_the_current_period(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-08 00:30:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
        ]);

        TenantSchedule::define(
            id: 'test.daily',
            cadence: new DailyCadence('00:30'),
            handler: FakeTenantScheduleHandler::class,
        );

        TenantScheduledRun::query()->create([
            'tenant_id' => $tenant->getKey(),
            'schedule_id' => 'test.daily',
            'period_key' => '2026-05-08',
            'status' => TenantScheduledRunStatus::Succeeded,
            'claimed_at' => now(),
            'attempts' => 1,
        ]);

        $dispatched = app(DispatchTenantSchedules::class)->execute();

        $this->assertSame(0, $dispatched);
        Queue::assertNothingPushed();
    }

    public function test_configured_registrar_registers_tenant_schedules_with_the_registry(): void
    {
        config(['multitenancy-kit.tenant_schedule_registrar' => FakeConfiguredTenantScheduleRegistrar::class]);

        app(RegisterConfiguredTenantSchedules::class)->execute();

        $definition = app(TenantScheduleRegistry::class)->get('test.configured');

        $this->assertSame('test.configured', $definition->id);
        $this->assertSame(FakeTenantScheduleHandler::class, $definition->handler);
    }

    public function test_registry_uses_configured_tenant_schedule_queue_when_no_queue_is_passed(): void
    {
        config(['multitenancy-kit.tenant_schedule_queue' => 'configured-queue']);

        $definition = app(TenantScheduleRegistry::class)->define(
            id: 'test.default-queue',
            cadence: Cadence::daily(),
            handler: FakeTenantScheduleHandler::class,
        );

        $this->assertSame('configured-queue', $definition->queue);
    }

    public function test_registry_uses_configured_tenant_schedule_queue_when_null_queue_is_passed(): void
    {
        config(['multitenancy-kit.tenant_schedule_queue' => 'configured-queue']);

        $definition = app(TenantScheduleRegistry::class)->define(
            id: 'test.null-queue',
            cadence: Cadence::daily(),
            handler: FakeTenantScheduleHandler::class,
            queue: null,
        );

        $this->assertSame('configured-queue', $definition->queue);
    }

    public function test_handler_parameters_are_passed_to_registered_schedule_handlers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
        ]);

        TenantSchedule::define(
            id: 'test.parameterized',
            cadence: Cadence::daily(),
            handler: FakeParameterizedTenantScheduleHandler::class,
            parameters: ['full' => true],
        );

        FakeParameterizedTenantScheduleHandler::$lastFull = null;

        (new RunTenantScheduledTask(
            tenantId: $tenant->getKey(),
            scheduleId: 'test.parameterized',
            force: true,
        ))->handle(
            app(TenantScheduleRegistry::class),
            app(ClaimTenantScheduledRun::class),
            app(ResolvesTenantTimezone::class),
        );

        $this->assertTrue(FakeParameterizedTenantScheduleHandler::$lastFull);
    }

    public function test_tenant_scheduled_task_job_is_not_handled_by_spatie_tenant_aware_queue_bootstrapper(): void
    {
        $this->assertInstanceOf(NotTenantAware::class, new RunTenantScheduledTask(
            tenantId: 1,
            scheduleId: 'test.daily',
        ));
    }

    public function test_claim_succeeds_once_and_reclaims_failed_runs_below_max_attempts(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
        ]);

        $claim = app(ClaimTenantScheduledRun::class);

        $firstClaim = $claim->execute($tenant, 'test.daily', '2026-05-08', maxAttempts: 3);
        $duplicateClaim = $claim->execute($tenant, 'test.daily', '2026-05-08', maxAttempts: 3);

        $this->assertInstanceOf(TenantScheduledRun::class, $firstClaim);
        $this->assertNull($duplicateClaim);

        $firstClaim->update([
            'status' => TenantScheduledRunStatus::Failed,
            'last_error' => 'Failed once',
        ]);

        $reclaimed = $claim->execute($tenant, 'test.daily', '2026-05-08', maxAttempts: 3);

        $this->assertInstanceOf(TenantScheduledRun::class, $reclaimed);
        $this->assertSame(2, $reclaimed->attempts);
        $this->assertSame(TenantScheduledRunStatus::Claimed, $reclaimed->status);
    }

    public function test_sweeper_marks_stale_claimed_and_running_runs_abandoned(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'First',
            'domain' => 'first.test',
            'database' => 'first',
        ]);

        TenantSchedule::define(
            id: 'test.daily',
            cadence: new DailyCadence('00:30'),
            handler: FakeTenantScheduleHandler::class,
            timeoutSeconds: 60,
            staleGraceSeconds: 30,
        );

        $staleRun = TenantScheduledRun::query()->create([
            'tenant_id' => $tenant->getKey(),
            'schedule_id' => 'test.daily',
            'period_key' => '2026-05-08',
            'status' => TenantScheduledRunStatus::Running,
            'claimed_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(10),
            'attempts' => 1,
        ]);

        $freshRun = TenantScheduledRun::query()->create([
            'tenant_id' => $tenant->getKey(),
            'schedule_id' => 'test.daily',
            'period_key' => '2026-05-09',
            'status' => TenantScheduledRunStatus::Claimed,
            'claimed_at' => now(),
            'attempts' => 1,
        ]);

        $abandoned = app(SweepStaleTenantScheduledRuns::class)->execute();

        $this->assertSame(1, $abandoned);
        $this->assertSame(TenantScheduledRunStatus::Abandoned, $staleRun->refresh()->status);
        $this->assertSame(TenantScheduledRunStatus::Claimed, $freshRun->refresh()->status);
    }
}

final readonly class FakeTenantScheduleHandler
{
    public function execute(bool $force = false): void {}
}

final class FakeParameterizedTenantScheduleHandler
{
    public static ?bool $lastFull = null;

    public function execute(bool $full = false): void
    {
        self::$lastFull = $full;
    }
}

final readonly class EnabledTenantOnly
{
    public function execute(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['enabled'] ?? false);
    }
}

final readonly class FakeConfiguredTenantScheduleRegistrar implements RegistersTenantSchedules
{
    public function register(TenantScheduleRegistry $registry): void
    {
        $registry->define(
            id: 'test.configured',
            cadence: new DailyCadence('00:30'),
            handler: FakeTenantScheduleHandler::class,
        );
    }
}
