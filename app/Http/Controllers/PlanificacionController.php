<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Simulacion;
use App\Services\Simulacion\Planificador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * M3 — Simulador de planificación: FCFS, SJF, SRTF, RR y Prioridades.
 * Los algoritmos están en App\Services\Simulacion\Planificador; aquí solo se validan
 * las entradas, se arman las respuestas JSON y se guardan las simulaciones en SQLite.
 */
class PlanificacionController extends Controller
{
    public function index(): View
    {
        return view('modulos.planificacion.index', ['algoritmos' => Planificador::ALGORITMOS]);
    }

    /** Simula el algoritmo elegido y además los cinco para la tabla comparativa. */
    public function simular(Request $request): JsonResponse
    {
        [$algoritmo, $procesos, $quantum] = $this->entrada($request);

        try {
            return response()->json([
                'simulacion' => Planificador::simular($algoritmo, $procesos, $quantum),
                'comparativa' => Planificador::comparar($procesos, $quantum),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }
    }

    /**
     * Guarda la simulación. El resultado se vuelve a calcular en el servidor:
     * no se confía en lo que envíe el navegador.
     */
    public function guardar(Request $request): JsonResponse
    {
        [$algoritmo, $procesos, $quantum] = $this->entrada($request);
        $nombre = $request->validate(['nombre' => ['nullable', 'string', 'max:80']])['nombre'] ?? null;

        try {
            $r = Planificador::simular($algoritmo, $procesos, $quantum);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        $simulacion = Simulacion::create([
            'user_id' => $request->user()->id,
            'nombre' => $nombre,
            'algoritmo' => $algoritmo,
            'quantum' => $r['quantum'],
            'procesos' => array_map(fn ($f) => [
                'id' => $f['id'], 'llegada' => $f['llegada'], 'rafaga' => $f['rafaga'], 'prioridad' => $f['prioridad'],
            ], $r['resultados']),
            'gantt' => $r['gantt'],
            'resultados' => $r['resultados'],
            'espera_promedio' => $r['espera_promedio'],
            'retorno_promedio' => $r['retorno_promedio'],
        ]);

        $etiqueta = Planificador::ALGORITMOS[$algoritmo].($r['quantum'] ? " q={$r['quantum']}" : '');
        Bitacora::registrar('GUARDAR_SIMULACION', 'exito', null, "#{$simulacion->id} {$etiqueta}, ".count($procesos).' procesos');

        return response()->json(['mensaje' => "Simulación #{$simulacion->id} guardada.", 'id' => $simulacion->id]);
    }

    /** Últimas simulaciones guardadas. */
    public function lista(): JsonResponse
    {
        $filas = Simulacion::query()
            ->leftJoin('users', 'users.id', '=', 'simulaciones.user_id')
            ->latest('simulaciones.created_at')
            ->limit(30)
            ->get(['simulaciones.id', 'simulaciones.nombre', 'algoritmo', 'quantum', 'espera_promedio', 'retorno_promedio', 'simulaciones.created_at', 'simulaciones.procesos', 'users.name as usuario']);

        return response()->json($filas->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'algoritmo' => Planificador::ALGORITMOS[$s->algoritmo] ?? $s->algoritmo,
            'quantum' => $s->quantum,
            'procesos' => count($s->procesos),
            'espera' => (float) $s->espera_promedio,
            'retorno' => (float) $s->retorno_promedio,
            'usuario' => $s->usuario ?? '—',
            'fecha' => $s->created_at->format('d/m/Y H:i'),
        ]));
    }

    /** Una simulación guardada, con la comparativa recalculada para sus procesos. */
    public function mostrar(Simulacion $simulacion): JsonResponse
    {
        $quantum = $simulacion->quantum ?? 2;

        return response()->json([
            'id' => $simulacion->id,
            'nombre' => $simulacion->nombre,
            'quantum' => $quantum,
            'procesos' => $simulacion->procesos,
            'simulacion' => [
                'algoritmo' => $simulacion->algoritmo,
                'quantum' => $simulacion->quantum,
                'gantt' => $simulacion->gantt,
                'resultados' => $simulacion->resultados,
                'espera_promedio' => (float) $simulacion->espera_promedio,
                'retorno_promedio' => (float) $simulacion->retorno_promedio,
            ],
            'comparativa' => Planificador::comparar($simulacion->procesos, $quantum),
        ]);
    }

    /**
     * @return array{0: string, 1: array, 2: int}
     */
    private function entrada(Request $request): array
    {
        $datos = $request->validate([
            'algoritmo' => ['required', Rule::in(array_keys(Planificador::ALGORITMOS))],
            'quantum' => ['nullable', 'integer', 'between:1,20'],
            'procesos' => ['required', 'array', 'min:1', 'max:15'],
            'procesos.*.llegada' => ['required', 'integer', 'between:0,100'],
            'procesos.*.rafaga' => ['required', 'integer', 'between:1,100'],
            'procesos.*.prioridad' => ['nullable', 'integer', 'between:0,100'],
        ], [
            'procesos.*.llegada.*' => 'Llegada: entero de 0 a 100 en cada proceso.',
            'procesos.*.rafaga.*' => 'Ráfaga: entero de 1 a 100 en cada proceso.',
            'procesos.*.prioridad.*' => 'Prioridad: entero de 0 a 100.',
        ]);

        $procesos = array_map(fn ($p) => [
            'llegada' => (int) $p['llegada'],
            'rafaga' => (int) $p['rafaga'],
            'prioridad' => (int) ($p['prioridad'] ?? 0),
        ], array_values($datos['procesos']));

        return [$datos['algoritmo'], $procesos, (int) ($datos['quantum'] ?? 2)];
    }
}
