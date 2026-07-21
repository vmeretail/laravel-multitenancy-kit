<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences;

use Carbon\CarbonImmutable;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\CadenceContract;

final readonly class MonthlyCadence implements CadenceContract
{
    public function __construct(
        private int $dayOfMonth,
        private string $time,
    ) {}

    public static function monthly(): self
    {
        return new self(1, '00:00');
    }

    public static function monthlyOn(int $dayOfMonth, string $time): self
    {
        return new self($dayOfMonth, $time);
    }

    public function isDue(CarbonImmutable $tenantNow): bool
    {
        return $tenantNow->greaterThanOrEqualTo($this->target($tenantNow));
    }

    public function periodKey(CarbonImmutable $tenantNow): string
    {
        return $tenantNow->format('Y-m');
    }

    public function targetDescription(CarbonImmutable $tenantNow): string
    {
        return $this->target($tenantNow)->format('Y-m-d H:i:s T');
    }

    private function target(CarbonImmutable $tenantNow): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));
        $day = min($this->dayOfMonth, $tenantNow->daysInMonth);

        return $tenantNow->setDay($day)->setTime($hour, $minute);
    }
}
