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
 *  3. Señales y renice solo sobre procesos de prueba (lo valida el controlador de M1).
 *  4. Acciones administrativas => rol Administrador + bitácora (middleware + Bitacora::registrar).
 *
 * Uso:
 *   $salida = ComandoSeguro::ejecutar('df', ['-h']);
 *   ComandoSeguro::ejecutar('kill', ['-TERM', (string) $pid]);
 */
final class ComandoSeguro
{
    /**
     * Comando lógico => ruta absoluta y argumentos fijos permitidos.
     * Cualquier comando que no esté aquí se rechaza.
     */
    private const PERMITIDOS = [
        // M1 — Procesos
        'ps' => '/usr/bin/ps',
        'kill' => '/usr/bin/kill',
        'renice' => '/usr/bin/renice',
        'sleep' => '/usr/bin/sleep',
        // M5 — Almacenamiento
        'lsblk' => '/usr/bin/lsblk',
        'df' => '/usr/bin/df',
        'findmnt' => '/usr/bin/findmnt',
        'who' => '/usr/bin/who',
    ];

    /** Señales aceptadas por M1. */
    public const SENALES = ['TERM', 'KILL', 'STOP', 'CONT'];

    /**
     * @param  list<string>  $argumentos
     */
    public static function ejecutar(string $comando, array $argumentos = []): string
    {
        if (! array_key_exists($comando, self::PERMITIDOS)) {
            throw new InvalidArgumentException("Comando no permitido: {$comando}");
        }

        $linea = escapeshellcmd(self::PERMITIDOS[$comando]);
        foreach ($argumentos as $arg) {
            $linea .= ' '.escapeshellarg((string) $arg);
        }

        $salida = [];
        $codigo = 0;
        exec($linea.' 2>&1', $salida, $codigo);

        if ($codigo !== 0) {
            throw new RuntimeException(implode("\n", $salida) ?: "El comando terminó con código {$codigo}");
        }

        return implode("\n", $salida);
    }

    /**
     * Valida que un PID recibido del formulario sea un entero positivo.
     */
    public static function pidValido(mixed $pid): int
    {
        if (! is_numeric($pid) || (int) $pid <= 1 || (string) (int) $pid !== (string) $pid) {
            throw new InvalidArgumentException('PID inválido');
        }

        return (int) $pid;
    }
}
