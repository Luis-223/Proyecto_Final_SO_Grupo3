<?php

namespace App\Services\Sistema;

/**
 * Lectura del sistema de archivos virtual /proc (M1 y M2).
 * No ejecuta comandos: solo lee archivos del kernel.
 */
final class ProcReader
{
    public static function leer(string $ruta): ?string
    {
        // Solo rutas dentro de /proc
        if (! str_starts_with($ruta, '/proc/') || str_contains($ruta, '..')) {
            return null;
        }

        $contenido = @file_get_contents($ruta);

        return $contenido === false ? null : $contenido;
    }

    /** /proc/loadavg => [1min, 5min, 15min] */
    public static function cargaPromedio(): array
    {
        $partes = explode(' ', trim((string) self::leer('/proc/loadavg')));

        return array_map('floatval', array_slice($partes, 0, 3));
    }

    /** /proc/uptime => segundos encendido */
    public static function uptime(): float
    {
        return (float) explode(' ', (string) self::leer('/proc/uptime'))[0];
    }

    /** /proc/meminfo => ['MemTotal' => kB, ...] */
    public static function memoria(): array
    {
        $datos = [];
        foreach (explode("\n", (string) self::leer('/proc/meminfo')) as $linea) {
            if (preg_match('/^(\w+(?:\(\w+\))?):\s+(\d+)/', $linea, $m)) {
                $datos[$m[1]] = (int) $m[2];
            }
        }

        return $datos;
    }

    /** PIDs actuales listando los directorios numéricos de /proc */
    public static function pids(): array
    {
        return array_values(array_map('intval', array_filter(
            scandir('/proc') ?: [],
            fn ($d) => ctype_digit($d)
        )));
    }

    /**
     * /proc/cpuinfo => modelo y cantidad de CPU lógicas (una entrada "processor" por núcleo/hilo).
     *
     * @return array{modelo: string, logicos: int, fisicos: int}
     */
    public static function cpuinfo(): array
    {
        $texto = (string) self::leer('/proc/cpuinfo');
        preg_match('/^model name\s*:\s*(.+)$/m', $texto, $modelo);
        $logicos = preg_match_all('/^processor\s*:/m', $texto);

        // Núcleos físicos: pares únicos (physical id, core id); si no existen se usa el total lógico.
        preg_match_all('/^physical id\s*:\s*(\d+)$/m', $texto, $fisicos);
        preg_match_all('/^core id\s*:\s*(\d+)$/m', $texto, $cores);
        $pares = [];
        foreach ($cores[1] as $i => $core) {
            $pares[($fisicos[1][$i] ?? '0').'-'.$core] = true;
        }

        return [
            'modelo' => trim($modelo[1] ?? 'Desconocido'),
            'logicos' => max(1, $logicos),
            'fisicos' => count($pares) ?: max(1, $logicos),
        ];
    }

    /**
     * Una lectura de /proc/stat: tiempos acumulados (en jiffies) de la CPU total y de cada núcleo.
     * El % de uso sale de comparar DOS lecturas (ver CpuMemoriaController).
     *
     * @return array<string, array{total: int, ocioso: int}>  ['cpu' => ..., 'cpu0' => ..., ...]
     */
    public static function tiemposCpu(): array
    {
        $tiempos = [];
        foreach (explode("\n", (string) self::leer('/proc/stat')) as $linea) {
            if (! preg_match('/^(cpu\d*)\s+(.+)$/', $linea, $m)) {
                continue;
            }
            // user nice system idle iowait irq softirq steal (guest ya está incluido en user)
            $v = array_map('intval', preg_split('/\s+/', trim($m[2])));
            $v = array_pad($v, 8, 0);
            $tiempos[$m[1]] = [
                'total' => $v[0] + $v[1] + $v[2] + $v[3] + $v[4] + $v[5] + $v[6] + $v[7],
                'ocioso' => $v[3] + $v[4],
            ];
        }

        return $tiempos;
    }

    /**
     * % de uso entre dos lecturas de /proc/stat: 100 × (1 − Δocioso / Δtotal).
     *
     * @return array<string, float>
     */
    public static function porcentajeCpu(array $antes, array $despues): array
    {
        $uso = [];
        foreach ($despues as $cpu => $t) {
            if (! isset($antes[$cpu])) {
                continue;
            }
            $total = $t['total'] - $antes[$cpu]['total'];
            $ocioso = $t['ocioso'] - $antes[$cpu]['ocioso'];
            $uso[$cpu] = $total > 0 ? round(100 * (1 - $ocioso / $total), 1) : 0.0;
        }

        return $uso;
    }
}
