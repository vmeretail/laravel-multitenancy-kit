<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Illuminate\Pipeline\Pipeline;
use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Models\CentralUser;

final readonly class SyncCentralUserToTenant
{
    public function execute(CentralUser $centralUser, IsTenant $tenant, ?string $previousEmail = null): void
    {
        $tenant->execute(function () use ($centralUser, $tenant, $previousEmail): void {
            $tenantUserModel = config('multitenancy-kit.tenant_user_model');
            $syncFields = config('multitenancy-kit.sync_fields');

            $attributes = collect($syncFields)
                ->mapWithKeys(fn (string $field): array => [$field => $centralUser->{$field}])
                ->put('central_user', true)
                ->all();

            $tenantUser = $tenantUserModel::firstOrNew(['email' => $centralUser->email]);

            if (! $tenantUser->exists && $previousEmail !== null) {
                $tenantUser = $tenantUserModel::query()
                    ->where('email', $previousEmail)
                    ->first() ?? $tenantUser;
            }

            $tenantUser->fill($attributes)->save();

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
