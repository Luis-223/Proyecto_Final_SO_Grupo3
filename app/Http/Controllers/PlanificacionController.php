<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * M3 — Simulador de planificación: FCFS, SJF, SRTF, RR y Prioridades.
 * Responsable: ver README.md (tabla de integrantes).
 * Avance 1: solo la pantalla base. La lógica se implementa en la rama del módulo.
 */
class PlanificacionController extends Controller
{
    public function index(): View
    {
        return view('modulos.planificacion.index');
    }
}
