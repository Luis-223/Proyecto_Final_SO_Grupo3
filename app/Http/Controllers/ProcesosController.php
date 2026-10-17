<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\ProcesoPrueba;
use App\Services\Sistema\ComandoSeguro;
use App\Services\Sistema\Procesos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * M1 — Procesos: tabla, árbol, procesos de prueba, señales y renice.
 *
 * Seguridad (enunciado, sección 6):
 *  - Lanzar, enviar señales y renice están en middleware('admin') (ver routes/web.php).
 *  - Solo se actúa sobre PIDs registrados en procesos_prueba, que sigan vivos,
 *    pertenezcan a www-data y cuyo comando sea el que lanzó la app (Procesos::esDePrueba).
 *  - Todo pasa por ComandoSeguro (lista blanca + escapeshellarg) y queda en la bitácora.
 */
class ProcesosController extends Controller
{
    /** Tipos de proceso de prueba: comando de la lista blanca + argumentos fijos. */
    private const TIPOS = [
        'sleep' => ['etiqueta' => 'sleep 600 (durmiendo, estado S)', 'detalle' => 'sleep 600'],
        'carga_cpu' => ['etiqueta' => 'Carga de CPU 120 s (estado R)', 'detalle' => 'php scripts/carga-cpu.php 120'],
    ];

    /** Máximo de procesos de prueba vivos al mismo tiempo. */
    private const MAX_PRUEBAS = 5;

    public function index(): View
    {
        return view('modulos.procesos.index', ['tipos' => self::TIPOS]);
    }

    /** JSON para la tabla y el árbol (la vista lo pide con fetch). */
    public function datos(): JsonResponse
    {
        $procesos = Procesos::instantanea(250);
        $pruebas = $this->pruebasVivas();
        $pidsPrueba = $pruebas->pluck('tipo', 'pid')->all();

        foreach ($procesos as &$p) {
            $p['prueba'] = $pidsPrueba[$p['pid']] ?? null;
        }

        return response()->json([
            'hora' => now()->format('H:i:s'),
            'procesos' => $procesos,
            'resumen' => Procesos::resumen($procesos),
            'pruebas' => $pruebas->map(fn ($r) => ['pid' => $r->pid, 'tipo' => $r->tipo, 'desde' => $r->created_at->format('H:i:s')])->values(),
        ]);
    }

    /** Lanza un proceso de prueba (sleep o carga de CPU). */
    public function lanzar(Request $request): JsonResponse
    {
        $tipo = $request->validate(['tipo' => ['required', Rule::in(array_keys(self::TIPOS))]])['tipo'];

        if ($this->pruebasVivas()->count() >= self::MAX_PRUEBAS) {
            Bitacora::registrar('LANZAR_PRUEBA', 'rechazado', null, self::TIPOS[$tipo]['detalle'], 'Límite de '.self::MAX_PRUEBAS.' procesos de prueba');

            return response()->json(['mensaje' => 'Ya hay '.self::MAX_PRUEBAS.' procesos de prueba activos. Termine alguno primero.'], 422);
        }

        try {
            $pid = match ($tipo) {
                'sleep' => ComandoSeguro::lanzar('sleep', ['600']),
                'carga_cpu' => ComandoSeguro::lanzar('php', [base_path('scripts/carga-cpu.php'), '120']),
            };
            usleep(150000); // tiempo para que el proceso aparezca en /proc

            if (! Procesos::esDePrueba($pid, $tipo)) {
                throw new RuntimeException("El proceso {$pid} no quedó en ejecución");
            }
        } catch (RuntimeException $e) {
            Bitacora::registrar('LANZAR_PRUEBA', 'error', null, self::TIPOS[$tipo]['detalle'], $e->getMessage());

            return response()->json(['mensaje' => 'No se pudo lanzar: '.$e->getMessage()], 500);
        }

        ProcesoPrueba::create(['pid' => $pid, 'tipo' => $tipo, 'user_id' => $request->user()->id]);
        Bitacora::registrar('LANZAR_PRUEBA', 'exito', $pid, self::TIPOS[$tipo]['detalle']);

        return response()->json(['mensaje' => "Proceso de prueba lanzado con PID {$pid}.", 'pid' => $pid]);
    }

    /** Envía SIGTERM, SIGKILL, SIGSTOP o SIGCONT a un proceso de prueba. */
    public function senal(Request $request, string $pid): JsonResponse
    {
        $senal = $request->validate(['senal' => ['required', Rule::in(ComandoSeguro::SENALES)]])['senal'];
        $accion = 'SIG'.$senal;

        [$pid, $registro, $error] = $this->procesoDePrueba($pid, $accion);
        if ($error) {
            return $error;
        }

        try {
            ComandoSeguro::ejecutar('kill', ['-'.$senal, (string) $pid]);
        } catch (RuntimeException $e) {
            Bitacora::registrar($accion, 'error', $pid, "kill -{$senal}", $e->getMessage());

            return response()->json(['mensaje' => "No se pudo enviar {$accion}: ".$e->getMessage()], 500);
        }

        if (in_array($senal, ['TERM', 'KILL'], true)) {
            // Se espera hasta medio segundo a que el proceso desaparezca
            for ($i = 0; $i < 5 && Procesos::existe($pid); $i++) {
                usleep(100000);
            }
            if (! Procesos::existe($pid)) {
                $registro->update(['activo' => false]);
            }
        }

        $estado = Procesos::existe($pid) ? Procesos::leer($pid)['estado'] : 'terminado';
        Bitacora::registrar($accion, 'exito', $pid, "kill -{$senal}", "Estado después: {$estado}");

        return response()->json(['mensaje' => "{$accion} enviada al PID {$pid}. Estado ahora: {$estado}."]);
    }

    /** Cambia el nice de un proceso de prueba. www-data solo puede subirlo (bajar la prioridad). */
    public function renice(Request $request, string $pid): JsonResponse
    {
        $nice = (int) $request->validate(['nice' => ['required', 'integer', 'between:0,19']])['nice'];

        [$pid, , $error] = $this->procesoDePrueba($pid, 'RENICE');
        if ($error) {
            return $error;
        }

        $actual = Procesos::leer($pid)['nice'] ?? 0;
        if ($nice < $actual) {
            Bitacora::registrar('RENICE', 'rechazado', $pid, "nice {$actual} → {$nice}", 'Un usuario sin privilegios no puede bajar el nice');

            return response()->json(['mensaje' => "No se puede bajar el nice de {$actual} a {$nice}: sin privilegios de root solo se puede subir (menor prioridad)."], 422);
        }

        try {
            // Forma "renice PRIORIDAD -p PID": PRIORIDAD es el valor absoluto de nice
            ComandoSeguro::ejecutar('renice', [(string) $nice, '-p', (string) $pid]);
        } catch (RuntimeException $e) {
            Bitacora::registrar('RENICE', 'error', $pid, "nice {$actual} → {$nice}", $e->getMessage());

            return response()->json(['mensaje' => 'No se pudo aplicar renice: '.$e->getMessage()], 500);
        }

        Bitacora::registrar('RENICE', 'exito', $pid, "nice {$actual} → {$nice}");

        return response()->json(['mensaje' => "PID {$pid}: nice {$actual} → {$nice}."]);
    }

    /**
     * Valida el PID y comprueba que sea un proceso de prueba vivo.
     *
     * @return array{0: int, 1: ?ProcesoPrueba, 2: ?JsonResponse}
     */
    private function procesoDePrueba(string $pid, string $accion): array
    {
        try {
            $pid = ComandoSeguro::pidValido($pid);
        } catch (InvalidArgumentException) {
            Bitacora::registrar($accion, 'rechazado', null, null, 'PID inválido');

            return [0, null, response()->json(['mensaje' => 'PID inválido.'], 422)];
        }

        $registro = ProcesoPrueba::where('pid', $pid)->where('activo', true)->latest()->first();

        if (! $registro || ! Procesos::esDePrueba($pid, $registro->tipo)) {
            $registro?->update(['activo' => false]);
            Bitacora::registrar($accion, 'rechazado', $pid, null, 'No es un proceso de prueba de la aplicación');

            return [$pid, null, response()->json(['mensaje' => "El PID {$pid} no es un proceso de prueba lanzado por la aplicación."], 403)];
        }

        return [$pid, $registro, null];
    }

    /** Procesos de prueba marcados activos que siguen vivos; los que murieron se desactivan. */
    private function pruebasVivas()
    {
        $activos = ProcesoPrueba::where('activo', true)->orderBy('created_at')->get();

        return $activos->filter(function (ProcesoPrueba $r) {
            if (Procesos::esDePrueba($r->pid, $r->tipo)) {
                return true;
            }
            $r->update(['activo' => false]);

            return false;
        })->values();
    }
}
