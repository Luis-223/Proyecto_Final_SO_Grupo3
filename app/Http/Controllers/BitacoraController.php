<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\View\View;

/**
 * M6 — Consulta de la bitácora de acciones administrativas.
 */
class BitacoraController extends Controller
{
    public function index(): View
    {
        $registros = Bitacora::with('usuario')->latest('created_at')->paginate(25);

        return view('modulos.bitacora.index', compact('registros'));
    }
}
