<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Support\TenantScheduling;

use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\DailyCadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\MonthlyCadence;
use VmeRetail\MultitenancyKit\Support\TenantScheduling\Cadences\WeeklyCadence;

final readonly class Cadence
{
    private function __construct() {}

    public static function daily(): DailyCadence
    {
        return DailyCadence::daily();
    }

    public static function dailyAt(string $time): DailyCadence
    {
        return DailyCadence::dailyAt($time);
    }

    public static function weekly(): WeeklyCadence
    {
        return WeeklyCadence::weekly();
    }

    public static function weeklyOn(string $day, string $time): WeeklyCadence
    {
        return WeeklyCadence::weeklyOn($day, $time);
    }

    public static function monthly(): MonthlyCadence
    {
        return MonthlyCadence::monthly();
    }

    public static function monthlyOn(int $dayOfMonth, string $time): MonthlyCadence
    {
        return MonthlyCadence::monthlyOn($dayOfMonth, $time);
    }
}
