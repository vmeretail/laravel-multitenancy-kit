<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use VmeRetail\MultitenancyKit\Filament\Resources\CentralUserResource;

final class EditCentralUser extends EditRecord
{
    protected static string $resource = CentralUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
