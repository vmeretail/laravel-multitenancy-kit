<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource;

final class ListCentralUsers extends ListRecords
{
    protected static string $resource = CentralUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
