<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource;

final class CreateCentralUser extends CreateRecord
{
    protected static string $resource = CentralUserResource::class;
}
