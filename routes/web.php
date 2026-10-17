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
| Las rutas /api/... devuelven JSON para las vistas (usan la misma sesión).
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
    Route::get('/api/procesos', [ProcesosController::class, 'datos'])->name('api.procesos');
    Route::middleware(['admin', 'throttle:30,1'])->group(function () {
        Route::post('/procesos/prueba', [ProcesosController::class, 'lanzar'])->name('procesos.lanzar');
        Route::post('/procesos/{pid}/senal', [ProcesosController::class, 'senal'])->name('procesos.senal');
        Route::post('/procesos/{pid}/renice', [ProcesosController::class, 'renice'])->name('procesos.renice');
    });

    // M2 — CPU y Memoria
    Route::get('/cpu-memoria', [CpuMemoriaController::class, 'index'])->name('cpu-memoria.index');
    Route::get('/api/cpu-memoria', [CpuMemoriaController::class, 'datos'])->name('api.cpu-memoria');

    // M3 — Planificación de CPU
    Route::get('/planificacion', [PlanificacionController::class, 'index'])->name('planificacion.index');
    Route::post('/planificacion/simular', [PlanificacionController::class, 'simular'])->name('planificacion.simular');
    Route::get('/planificacion/simulaciones', [PlanificacionController::class, 'lista'])->name('planificacion.lista');
    Route::get('/planificacion/simulaciones/{simulacion}', [PlanificacionController::class, 'mostrar'])->name('planificacion.mostrar');
    Route::post('/planificacion/guardar', [PlanificacionController::class, 'guardar'])->middleware('admin')->name('planificacion.guardar');

    // M4 — Interbloqueos
    Route::get('/interbloqueos', [InterbloqueosController::class, 'index'])->name('interbloqueos.index');
    Route::post('/interbloqueos/evaluar', [InterbloqueosController::class, 'evaluar'])->name('interbloqueos.evaluar');
    Route::post('/interbloqueos/solicitud', [InterbloqueosController::class, 'solicitud'])->name('interbloqueos.solicitud');

    // M5 — Almacenamiento
    Route::get('/almacenamiento', [AlmacenamientoController::class, 'index'])->name('almacenamiento.index');

    // M6 — Bitácora (solo Administrador)
    Route::get('/bitacora', [BitacoraController::class, 'index'])->middleware('admin')->name('bitacora.index');
});
