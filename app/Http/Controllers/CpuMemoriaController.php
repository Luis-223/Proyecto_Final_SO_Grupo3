<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * M2 — CPU y Memoria: /proc/cpuinfo, /proc/stat, /proc/meminfo, /proc/loadavg.
 * Responsable: ver README.md (tabla de integrantes).
 * Avance 1: solo la pantalla base. La lógica se implementa en la rama del módulo.
 */
class CpuMemoriaController extends Controller
{
    public function index(): View
    {
        return view('modulos.cpu-memoria.index');
    }
}
