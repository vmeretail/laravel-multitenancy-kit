<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Spatie\Multitenancy\Models\Tenant;
use VmeRetail\MultitenancyKit\Models\ImpersonationToken;

final class ImpersonateUserController
{
    public function __invoke(string $token): RedirectResponse
    {
        $impersonationToken = ImpersonationToken::where('token', $token)->firstOrFail();

        $currentTenant = Tenant::current();

        abort_unless(
            $currentTenant && $currentTenant->id === $impersonationToken->tenant_id,
            403,
            'Token does not belong to the current tenant.',
        );

        $guard = $impersonationToken->auth_guard;
        $tenantUserModel = config('multitenancy-kit.tenant_user_model');
        $user = $tenantUserModel::findOrFail($impersonationToken->tenant_user_id);

        Auth::guard($guard)->login($user);

        $redirectUrl = $impersonationToken->redirect_url ?? '/';
        //        $impersonationToken->delete();

        return redirect($redirectUrl);
    }
}
