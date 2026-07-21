<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Filament\Resources\TenantResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use VmeRetail\MultitenancyKit\Actions\DeleteTenant;
use VmeRetail\MultitenancyKit\Filament\Resources\TenantResource;

final class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->color('danger')
                ->icon(Heroicon::Trash)
                ->modalIcon(Heroicon::OutlinedTrash)
                ->modalHeading('Delete Tenant')
                ->modalDescription('Are you sure you want to delete this tenant? This action cannot be undone.')
                ->requiresConfirmation()
                ->schema([
                    Checkbox::make('drop_database')
                        ->label('Also delete the tenant database')
                        ->helperText('If checked, the tenant\'s database will be permanently dropped.'),
                ])
                ->action(function (array $data): void {
                    app(DeleteTenant::class)->execute(
                        tenant: $this->record,
                        dropDatabase: $data['drop_database'] ?? false,
                    );

                    $this->redirect(TenantResource::getUrl('index'));
                }),
        ];
    }
}
