<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences;

use Carbon\CarbonImmutable;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\CadenceContract;

final readonly class DailyCadence implements CadenceContract
{
    public function __construct(
        private string $time,
    ) {}

    public static function daily(): self
    {
        return new self('00:00');
    }

    public static function dailyAt(string $time): self
    {
        return new self($time);
    }

    public function isDue(CarbonImmutable $tenantNow): bool
    {
        return $tenantNow->greaterThanOrEqualTo($this->target($tenantNow));
    }

    public function periodKey(CarbonImmutable $tenantNow): string
    {
        return $tenantNow->format('Y-m-d');
    }

    public function targetDescription(CarbonImmutable $tenantNow): string
    {
        return $this->target($tenantNow)->format('Y-m-d H:i:s T');
    }

    private function target(CarbonImmutable $tenantNow): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));

        return $tenantNow->setTime($hour, $minute);
    }
}
