<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\TenantResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use VmeRetail\MultitenancyKit\Filament\Resources\TenantResource;

final class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
