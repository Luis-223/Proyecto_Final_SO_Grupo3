<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * M4 — Algoritmo del banquero y grafo de asignación de recursos.
 * Responsable: ver README.md (tabla de integrantes).
 * Avance 1: solo la pantalla base. La lógica se implementa en la rama del módulo.
 */
class InterbloqueosController extends Controller
{
    public function index(): View
    {
        return view('modulos.interbloqueos.index');
    }
}
