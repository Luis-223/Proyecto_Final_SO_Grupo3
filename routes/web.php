<?php

use App\Http\Controllers\AlmacenamientoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CpuMemoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterbloqueosController;
use App\Http\Controllers\PlanificacionController;
use App\Http\Controllers\ProcesosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SysMonitor Web — Rutas
|--------------------------------------------------------------------------
| Convención: cada módulo agrega sus rutas dentro de su bloque y solo en su
| rama (feature/mX-...), para evitar conflictos al hacer merge.
| Las rutas que modifican el sistema van dentro de middleware('admin').
*/

// M6 — Autenticación (sin registro de usuarios)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'entrar'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'salir'])->name('logout');

    // M6 — Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // M1 — Procesos
    Route::get('/procesos', [ProcesosController::class, 'index'])->name('procesos.index');
    Route::middleware('admin')->group(function () {
        // Avance 2: POST /procesos/prueba, POST /procesos/{pid}/senal, POST /procesos/{pid}/renice
    });

    // M2 — CPU y Memoria
    Route::get('/cpu-memoria', [CpuMemoriaController::class, 'index'])->name('cpu-memoria.index');
    // Avance 2: GET /api/cpu-memoria (JSON para las gráficas con fetch cada 2-5 s)

    // M3 — Planificación de CPU
    Route::get('/planificacion', [PlanificacionController::class, 'index'])->name('planificacion.index');

    // M4 — Interbloqueos
    Route::get('/interbloqueos', [InterbloqueosController::class, 'index'])->name('interbloqueos.index');

    // M5 — Almacenamiento
    Route::get('/almacenamiento', [AlmacenamientoController::class, 'index'])->name('almacenamiento.index');

    // M6 — Bitácora (solo Administrador)
    Route::get('/bitacora', [BitacoraController::class, 'index'])->middleware('admin')->name('bitacora.index');
});
