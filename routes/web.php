<?php

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\MaintenanceController;
use App\Http\Controllers\Portal\MediaController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\Portal\ServiceController;
use App\Http\Controllers\Portal\StatementController;
use App\Http\Controllers\Portal\TutorialController;
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

    // Estado de cuenta en pantalla (pestaña nueva, sin AppLayout)
    Route::get('/estado-cuenta', [StatementController::class, 'view'])->name('statement.view');
    Route::get('/estado-cuenta/descargar', [StatementController::class, 'downloadAll'])->name('statement.download-all');

    // Sección de tutoriales (visible para todos los clientes, con enlace en el SideNav)
    Route::get('/tutoriales', [TutorialController::class, 'index'])->name('tutorials.index');

    /*
     * Panel de gestión de tutoriales: OCULTO a propósito (ningún enlace en la
     * interfaz). Se entra escribiendo la URL: /gestion-tutoriales
     *
     * Si se define TUTORIALS_ADMIN_KEY en el .env, el panel pide la llave una
     * sola vez con /gestion-tutoriales?llave=LA_LLAVE y la recuerda en sesión.
     */
    Route::prefix('gestion-tutoriales')
        ->name('tutorials.')
        ->group(function () {
            Route::get('/', [TutorialController::class, 'manage'])->name('manage');
            Route::post('/', [TutorialController::class, 'store'])->name('store');
            Route::delete('/{tutorial}', [TutorialController::class, 'destroy'])->name('destroy');
        });

    // Abonos del cliente (validación manual por el ERP)
    Route::post('/abonos', [PortalPaymentController::class, 'store'])->name('portal-payments.store');

    // Descarga segura de comprobantes
    Route::get('/comprobantes/{media}', [MediaController::class, 'show'])->name('media.download');

    /*
     * Mantenimiento por URL (servidores sin terminal tipo HostGator).
     * Solo requieren tener la sesión del portal abierta.
     */
    Route::prefix('mantenimiento')->name('maintenance.')->group(function () {
        Route::get('limpiar-cache', [MaintenanceController::class, 'clearCache'])->name('cache');
        Route::get('storage-link', [MaintenanceController::class, 'storageLink'])->name('storage-link');
        Route::get('tabla-tutoriales', [MaintenanceController::class, 'migrateTutorials'])->name('migrate-tutorials');
    });
});
