<?php

namespace App\Http\Controllers;

use App\Services\Sistema\ProcReader;
use App\Services\Sistema\Procesos;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * M2 — CPU y Memoria: /proc/cpuinfo, /proc/stat, /proc/meminfo, /proc/loadavg, /proc/uptime.
 * La vista pide /api/cpu-memoria con fetch cada 2–5 s y actualiza las gráficas.
 */
class CpuMemoriaController extends Controller
{
    public function index(): View
    {
        return view('modulos.cpu-memoria.index', ['cpu' => ProcReader::cpuinfo()]);
    }

    public function datos(): JsonResponse
    {
        // Dos lecturas de /proc/stat separadas ~300 ms. Entre ambas se toma también
        // la instantánea de procesos, que mide el % de CPU de cada uno en la misma ventana.
        $antes = ProcReader::tiemposCpu();
        $procesos = Procesos::instantanea(300);
        $despues = ProcReader::tiemposCpu();
        $uso = ProcReader::porcentajeCpu($antes, $despues);

        $mem = ProcReader::memoria();
        $total = $mem['MemTotal'] ?? 0;
        $libre = $mem['MemFree'] ?? 0;
        $cache = ($mem['Buffers'] ?? 0) + ($mem['Cached'] ?? 0) + ($mem['SReclaimable'] ?? 0);
        $usada = max(0, $total - $libre - $cache); // mismo cálculo que el comando free
        $swapTotal = $mem['SwapTotal'] ?? 0;
        $swapUsada = $swapTotal - ($mem['SwapFree'] ?? 0);

        // Top 5: se ordena la lista de procesos de mayor a menor por el campo pedido
        $top = function (string $campo) use ($procesos): array {
            usort($procesos, fn ($a, $b) => $b[$campo] <=> $a[$campo]);

            return array_map(
                fn ($p) => ['pid' => $p['pid'], 'comando' => $p['nombre'], 'usuario' => $p['usuario'], 'cpu' => $p['cpu'], 'mem_kb' => $p['mem_kb'], 'mem_pct' => $p['mem_pct']],
                array_slice($procesos, 0, 5)
            );
        };

        $cpuinfo = ProcReader::cpuinfo();
        $uptime = ProcReader::uptime();

        return response()->json([
            'hora' => now()->format('H:i:s'),
            'cpu' => [
                'modelo' => $cpuinfo['modelo'],
                'nucleos' => $cpuinfo['logicos'],
                'total' => $uso['cpu'] ?? 0,
                'por_nucleo' => array_values(array_filter($uso, fn ($k) => $k !== 'cpu', ARRAY_FILTER_USE_KEY)),
            ],
            'carga' => ProcReader::cargaPromedio(),
            'uptime' => ['segundos' => (int) $uptime, 'texto' => self::duracion($uptime)],
            'memoria' => [
                'total_kb' => $total,
                'usada_kb' => $usada,
                'cache_kb' => $cache,
                'libre_kb' => $libre,
                'disponible_kb' => $mem['MemAvailable'] ?? 0,
                'pct' => $total ? round(100 * $usada / $total, 1) : 0,
            ],
            'swap' => [
                'total_kb' => $swapTotal,
                'usada_kb' => $swapUsada,
                'pct' => $swapTotal ? round(100 * $swapUsada / $swapTotal, 1) : 0,
            ],
            'top_cpu' => $top('cpu'),
            'top_memoria' => $top('mem_kb'),
        ]);
    }

    /** 93784 s => "1d 2h 3m" */
    public static function duracion(float $segundos): string
    {
        $s = (int) $segundos;

        return sprintf('%dd %dh %dm', intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60));
    }
}
