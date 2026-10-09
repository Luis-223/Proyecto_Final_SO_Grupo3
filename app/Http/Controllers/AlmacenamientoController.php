<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * M5 — Discos, particiones, sistemas de archivos y usuarios.
 * Responsable: ver README.md (tabla de integrantes).
 * Avance 1: solo la pantalla base. La lógica se implementa en la rama del módulo.
 */
class AlmacenamientoController extends Controller
{
    public function index(): View
    {
        return view('modulos.almacenamiento.index');
    }
}
