<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Enums;

enum TenantScheduledRunStatus: string
{
    case Claimed = 'claimed';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
}
