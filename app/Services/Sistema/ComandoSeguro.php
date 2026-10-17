<?php

namespace App\Services\Sistema;

use InvalidArgumentException;
use RuntimeException;

/**
 * Única puerta de salida hacia el shell en toda la aplicación.
 *
 * Reglas de seguridad del enunciado (sección 6):
 *  1. Nunca se concatena texto del usuario: cada argumento pasa por escapeshellarg().
 *  2. Lista blanca: solo se ejecutan los comandos definidos en PERMITIDOS.
 *  3. Señales y renice solo sobre procesos de prueba (lo valida Procesos::verificarPrueba).
 *  4. Acciones administrativas => rol Administrador + bitácora (middleware + Bitacora::registrar).
 *
 * Uso:
 *   $salida = ComandoSeguro::ejecutar('df', ['-h']);
 *   ComandoSeguro::ejecutar('kill', ['-TERM', (string) $pid]);
 *   $pid = ComandoSeguro::lanzar('sleep', ['600']);
 */
final class ComandoSeguro
{
    /**
     * Comando lógico => ruta absoluta del ejecutable.
     * Cualquier comando que no esté aquí se rechaza.
     */
    private const PERMITIDOS = [
        // M1 — Procesos
        'ps' => '/usr/bin/ps',
        'kill' => '/usr/bin/kill',
        'renice' => '/usr/bin/renice',
        'sleep' => '/usr/bin/sleep',
        'php' => '/usr/bin/php',          // solo para scripts/carga-cpu.php
        // M5 — Almacenamiento
        'lsblk' => '/usr/bin/lsblk',
        'df' => '/usr/bin/df',
        'findmnt' => '/usr/bin/findmnt',
        'who' => '/usr/bin/who',
    ];

    /** Comandos que se pueden lanzar en segundo plano como procesos de prueba. */
    private const LANZABLES = ['sleep', 'php'];

    /** Señales aceptadas por M1. */
    public const SENALES = ['TERM', 'KILL', 'STOP', 'CONT'];

    /**
     * Ejecuta un comando de la lista blanca y devuelve su salida.
     *
     * @param  list<string>  $argumentos
     */
    public static function ejecutar(string $comando, array $argumentos = []): string
    {
        $salida = [];
        $codigo = 0;
        exec(self::linea($comando, $argumentos).' 2>&1', $salida, $codigo);

        if ($codigo !== 0) {
            throw new RuntimeException(implode("\n", $salida) ?: "El comando terminó con código {$codigo}");
        }

        return implode("\n", $salida);
    }

    /**
     * Lanza un proceso en segundo plano y devuelve su PID.
     * La salida se descarta; el shell imprime $! (PID del último proceso en segundo plano).
     *
     * @param  list<string>  $argumentos
     */
    public static function lanzar(string $comando, array $argumentos = []): int
    {
        if (! in_array($comando, self::LANZABLES, true)) {
            throw new InvalidArgumentException("No se permite lanzar en segundo plano: {$comando}");
        }

        $salida = [];
        exec(self::linea($comando, $argumentos).' > /dev/null 2>&1 & echo $!', $salida);
        $pid = (int) trim((string) end($salida));

        if ($pid <= 1) {
            throw new RuntimeException('No se pudo obtener el PID del proceso lanzado');
        }

        return $pid;
    }

    /**
     * Valida que un PID recibido del formulario sea un entero mayor que 1.
     */
    public static function pidValido(mixed $pid): int
    {
        if (! is_numeric($pid) || (int) $pid <= 1 || (string) (int) $pid !== (string) $pid) {
            throw new InvalidArgumentException('PID inválido');
        }

        return (int) $pid;
    }

    /**
     * Arma la línea de comando: ruta fija de la lista blanca + argumentos escapados.
     *
     * @param  list<string>  $argumentos
     */
    private static function linea(string $comando, array $argumentos): string
    {
        if (! array_key_exists($comando, self::PERMITIDOS)) {
            throw new InvalidArgumentException("Comando no permitido: {$comando}");
        }

        $linea = escapeshellcmd(self::PERMITIDOS[$comando]);
        foreach ($argumentos as $arg) {
            $linea .= ' '.escapeshellarg((string) $arg);
        }

        return $linea;
    }
}
