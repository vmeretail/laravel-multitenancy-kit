<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Models;

use Illuminate\Database\Eloquent\Model;
use VmeRetail\MultitenancyKit\Enums\TenantScheduledRunStatus;

final class TenantScheduledRun extends Model
{
    protected $guarded = [];

    public function getConnectionName(): string
    {
        return config('multitenancy-kit.landlord_database_connection_name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantScheduledRunStatus::class,
            'claimed_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
