<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\TenantResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use VmeRetail\MultitenancyKit\Filament\Resources\TenantResource;

final class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
