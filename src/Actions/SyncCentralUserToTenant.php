<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Illuminate\Pipeline\Pipeline;
use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final readonly class SyncCentralUserToTenant
{
    public function execute(CentralUser $centralUser, IsTenant $tenant): void
    {
        $tenant->execute(function () use ($centralUser, $tenant): void {
            $tenantUserModel = config('multitenancy-kit.tenant_user_model');
            $syncFields = config('multitenancy-kit.sync_fields');

            $attributes = collect($syncFields)
                ->mapWithKeys(fn (string $field) => [$field => $centralUser->{$field}])
                ->put('central_user', true)
                ->all();

            $tenantUser = $tenantUserModel::updateOrCreate(
                ['email' => $centralUser->email],
                $attributes,
            );

            $pipeline = config('multitenancy-kit.after_sync_pipeline', []);

            if ($pipeline !== []) {
                app(Pipeline::class)
                    ->send([
                        'central_user' => $centralUser,
                        'tenant_user' => $tenantUser,
                        'tenant' => $tenant,
                    ])
                    ->through($pipeline)
                    ->thenReturn();
            }
        });
    }
}
