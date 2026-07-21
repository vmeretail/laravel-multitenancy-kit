<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VmeRetail\MultitenancyKit\Http\Controllers\ImpersonateUserController;
use VmeRetail\MultitenancyKit\Http\Controllers\LogoutImpersonateUserController;

Route::middleware('web')->group(function () {
    Route::get('/impersonate/{token}', ImpersonateUserController::class)
        ->name('multitenancy-kit.impersonate');

    $tenantGuard = config('multitenancy-kit.tenant_auth_guard', 'tenant');

    Route::post('/logout-impersonate', LogoutImpersonateUserController::class)
        ->middleware("auth:{$tenantGuard}")
        ->name('multitenancy-kit.logout-impersonate');
});
