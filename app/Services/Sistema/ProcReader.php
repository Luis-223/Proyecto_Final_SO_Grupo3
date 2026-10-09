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
            if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
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
}
