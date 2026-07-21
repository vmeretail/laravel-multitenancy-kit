<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Actions;

use Spatie\Multitenancy\Contracts\IsTenant;
use VmeRetail\MultitenancyKit\Models\ImpersonationToken;

final readonly class CreateImpersonationToken
{
    public function execute(IsTenant $tenant, int $tenantUserId, ?string $redirectUrl = null): ImpersonationToken
    {
        $guard = config('multitenancy-kit.tenant_auth_guard');

        return ImpersonationToken::create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $tenantUserId,
            'auth_guard' => $guard,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
