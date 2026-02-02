<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\RegistrationController;
use App\Http\Controllers\Web\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('web.login');
    Route::post('/login', [AuthController::class, 'login'])->name('web.login.submit');
    Route::post('/login/company', [AuthController::class, 'loginWithCompany'])->name('web.login.company');

    Route::get('/register', [RegistrationController::class, 'showRegister'])->name('web.register');
    Route::post('/register', [RegistrationController::class, 'register'])->name('web.register.submit');
});

Route::prefix('{companySlug}/web')->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('tenant.web.login');
    Route::post('/login', [TenantAuthController::class, 'login'])->name('tenant.web.login.submit');
});

// Web routes mirroring API structure (views)
Route::prefix('auth')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Web\GlobalAuthWebController::class, 'showLogin'])->name('auth.web.login');
    Route::post('/global-login', [\App\Http\Controllers\Web\GlobalAuthWebController::class, 'globalLogin'])->name('auth.web.global-login');
    Route::post('/login-with-company', [\App\Http\Controllers\Web\GlobalAuthWebController::class, 'loginWithCompany'])->name('auth.web.login.with.company');
});

Route::prefix('registration')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Modules\Sales\Web\TenantRegistrationController::class, 'showRegister'])->name('registration.web.register');
    Route::post('/register', [\App\Http\Controllers\Modules\Sales\Web\TenantRegistrationController::class, 'register'])->name('registration.web.register.submit');
});

Route::post('/tenants/register', [\App\Http\Controllers\Modules\Sales\Web\TenantRegistrationController::class, 'register'])->name('registration.web.tenants.register');

Route::prefix('{companySlug}')
    ->middleware(['resolve.tenant.from.path', 'ensure.subscription'])
    ->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/login', [\App\Http\Controllers\Modules\Sales\Web\TenantAuthController::class, 'showLogin'])->name('tenant.web.auth.login');
            Route::post('/login', [\App\Http\Controllers\Modules\Sales\Web\TenantAuthController::class, 'login'])->name('tenant.web.auth.login.submit');
        });

        Route::get('/modules/{moduleId}/branches/{branchId}/check', [\App\Http\Controllers\Modules\Sales\Web\BranchCheckController::class, 'show'])
            ->middleware('ensure.branch.access')
            ->name('tenant.web.branch.check');
    });
