<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\CadenceContract;

final readonly class WeeklyCadence implements CadenceContract
{
    private const array DAYS = [
        'monday' => 0,
        'tuesday' => 1,
        'wednesday' => 2,
        'thursday' => 3,
        'friday' => 4,
        'saturday' => 5,
        'sunday' => 6,
    ];

    public function __construct(
        private string $day,
        private string $time,
    ) {
        if (! array_key_exists(mb_strtolower($this->day), self::DAYS)) {
            throw new InvalidArgumentException("Unsupported weekday [{$this->day}].");
        }
    }

    public static function weekly(): self
    {
        return new self('sunday', '00:00');
    }

    public static function weeklyOn(string $day, string $time): self
    {
        return new self($day, $time);
    }

    public function isDue(CarbonImmutable $tenantNow): bool
    {
        return $tenantNow->greaterThanOrEqualTo($this->target($tenantNow));
    }

    public function periodKey(CarbonImmutable $tenantNow): string
    {
        return $tenantNow->format('o-\WW');
    }

    public function targetDescription(CarbonImmutable $tenantNow): string
    {
        return $this->target($tenantNow)->format('Y-m-d H:i:s T');
    }

    private function target(CarbonImmutable $tenantNow): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));
        $dayOffset = self::DAYS[mb_strtolower($this->day)];

        return $tenantNow->startOfWeek()->addDays($dayOffset)->setTime($hour, $minute);
    }
}
