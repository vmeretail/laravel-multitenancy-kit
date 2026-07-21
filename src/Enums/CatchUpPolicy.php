<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Enums;

enum CatchUpPolicy: string
{
    case SkipMissed = 'skip_missed';
    case RunLatestOnly = 'run_latest_only';
    case RunAllMissed = 'run_all_missed';
}
