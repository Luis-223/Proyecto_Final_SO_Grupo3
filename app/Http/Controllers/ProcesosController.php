<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * M1 — Procesos: tabla, árbol, procesos de prueba, señales y renice.
 * Responsable: ver README.md (tabla de integrantes).
 * Avance 1: solo la pantalla base. La lógica se implementa en la rama del módulo.
 */
class ProcesosController extends Controller
{
    public function index(): View
    {
        return view('modulos.procesos.index');
    }
}
