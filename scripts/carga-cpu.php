<?php

/**
 * M1 — Proceso de prueba "carga de CPU".
 * Lo lanza la aplicación (usuario www-data) para observar un proceso en estado R
 * consumiendo CPU, enviarle señales y cambiar su prioridad con renice.
 * Termina solo después de N segundos (máximo 300) para no dejar carga olvidada.
 *
 * Uso: php scripts/carga-cpu.php 120
 */
$segundos = min(300, max(1, (int) ($argv[1] ?? 120)));
$fin = microtime(true) + $segundos;
$x = 0;

while (microtime(true) < $fin) {
    for ($i = 0; $i < 100000; $i++) {
        $x = ($x + $i * $i) % 1000003; // trabajo de CPU sin entrada/salida
    }
}
