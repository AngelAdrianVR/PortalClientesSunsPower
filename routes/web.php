<?php

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\MediaController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\Portal\ServiceController;
use App\Http\Controllers\Portal\StatementController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Raíz: animación de carga inicial (después redirige al login o al dashboard).
Route::get('/', function () {
    return Inertia::render('Loading', [
        'authenticated' => Auth::guard('portal')->check(),
    ]);
});

// Login / logout del portal. La identidad proviene de la tabla `clients`.
Route::middleware('guest')->group(function () {
    Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');
});

Route::post('/logout', [PortalAuthController::class, 'logout'])
    ->middleware('auth:portal')
    ->name('logout');

// Zona autenticada del portal
Route::middleware('auth:portal')->group(function () {
    // Panel general
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Módulo de servicios
    Route::get('/servicios', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/servicios/{serviceOrder}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/servicios/{serviceOrder}/estado-cuenta', [StatementController::class, 'download'])->name('services.statement');

    // Abonos del cliente (validación manual por el ERP)
    Route::post('/abonos', [PortalPaymentController::class, 'store'])->name('portal-payments.store');

    // Descarga segura de comprobantes
    Route::get('/comprobantes/{media}', [MediaController::class, 'show'])->name('media.download');
});
