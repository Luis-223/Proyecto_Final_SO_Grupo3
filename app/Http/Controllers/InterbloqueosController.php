<?php

namespace App\Http\Controllers;

use App\Services\Simulacion\Banquero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * M4 — Algoritmo del banquero y grafo de asignación de recursos.
 * La lógica está en App\Services\Simulacion\Banquero; aquí se reciben las matrices
 * desde la vista (fetch) y se devuelve el análisis en JSON.
 */
class InterbloqueosController extends Controller
{
    public function index(): View
    {
        return view('modulos.interbloqueos.index');
    }

    /** Necesidad, algoritmo de seguridad paso a paso, grafo, ciclos y recuperación. */
    public function evaluar(Request $request): JsonResponse
    {
        $d = $this->matrices($request);

        try {
            return response()->json(Banquero::evaluar($d['asignacion'], $d['maximo'], $d['disponible']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }
    }

    /** ¿Se concede la solicitud de recursos del proceso Pi? */
    public function solicitud(Request $request): JsonResponse
    {
        $d = $this->matrices($request);
        $extra = $request->validate([
            'proceso' => ['required', 'integer', 'between:0,9'],
            'pedido' => ['required', 'array', 'max:6'],
            'pedido.*' => ['nullable', 'integer', 'between:0,99'],
        ]);

        try {
            $estado = Banquero::preparar($d['asignacion'], $d['maximo'], $d['disponible']);
            $r = Banquero::solicitud($estado, (int) $extra['proceso'], array_map('intval', $extra['pedido']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return response()->json($r);
    }

    private function matrices(Request $request): array
    {
        return $request->validate([
            'asignacion' => ['required', 'array', 'min:1', 'max:10'],
            'asignacion.*' => ['required', 'array', 'max:6'],
            'asignacion.*.*' => ['required', 'integer', 'between:0,99'],
            'maximo' => ['required', 'array', 'min:1', 'max:10'],
            'maximo.*' => ['required', 'array', 'max:6'],
            'maximo.*.*' => ['required', 'integer', 'between:0,99'],
            'disponible' => ['required', 'array', 'min:1', 'max:6'],
            'disponible.*' => ['required', 'integer', 'between:0,99'],
        ], [
            '*.required' => 'Complete todas las celdas de las matrices.',
            'asignacion.*.*.*' => 'Asignación: enteros de 0 a 99.',
            'maximo.*.*.*' => 'Máximo: enteros de 0 a 99.',
            'disponible.*.*' => 'Disponible: enteros de 0 a 99.',
        ]);
    }
}
