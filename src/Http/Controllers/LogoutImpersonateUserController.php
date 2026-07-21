<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutImpersonateUserController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $guard = config('multitenancy-kit.tenant_auth_guard');
        $user = Auth::guard($guard)->user();

        abort_unless($user && $user->central_user, 403, 'Not an impersonated user.');

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirect = config('multitenancy-kit.exit_impersonation_redirect');

        if (! $redirect) {
            $centralDomain = config('multitenancy-kit.central_domain');
            $panelPath = config('multitenancy-kit.landlord_panel_path');
            $scheme = $request->isSecure() ? 'https' : 'http';
            $redirect = "{$scheme}://{$centralDomain}{$panelPath}";
        }

        return redirect($redirect);
    }
}
