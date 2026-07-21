<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Observers;

use Illuminate\Support\Str;
use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Events\TenantCreated;
use VmeRetail\MultitenancyKit\Models\Tenant;

final class TenantObserver
{
    public function creating(IsTenant $tenant): void
    {
        $slug = Str::slug($tenant->name, '_');
        $tenant->database = Tenant::generateDatabaseName($slug);
    }

    public function created(IsTenant $tenant): void
    {
        event(new TenantCreated($tenant));
    }
}
