<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\TenantResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use VmeRetail\MultitenancyKit\Filament\Resources\TenantResource;

final class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;
}
