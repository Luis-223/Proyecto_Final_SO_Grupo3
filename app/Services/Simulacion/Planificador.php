<?php

namespace App\Services\Simulacion;

use InvalidArgumentException;

/**
 * M3 — Algoritmos de planificación de CPU (simulados).
 *
 * Entrada: lista de procesos ['id' => 'P1', 'llegada' => 0, 'rafaga' => 5, 'prioridad' => 3].
 * Salida de cada algoritmo:
 *   gantt      => [['proceso' => 'P1', 'inicio' => 0, 'fin' => 5], ...]  ('—' = CPU ociosa)
 *   resultados => por proceso: fin, retorno (fin − llegada) y espera (retorno − ráfaga)
 *   promedios de espera y retorno
 *
 * Criterios de desempate (se explican en la defensa):
 *   - Si dos procesos empatan, gana el que llegó primero y luego el de menor índice (P1 antes que P2).
 *   - Prioridad: número menor = mayor prioridad. Versión NO expropiativa.
 *   - Round Robin: los procesos que llegan durante un quantum entran a la cola ANTES
 *     que el proceso que acaba de ser expropiado.
 */
final class Planificador
{
    public const ALGORITMOS = [
        'FCFS' => 'FCFS',
        'SJF' => 'SJF',
        'SRTF' => 'SRTF',
        'RR' => 'Round Robin',
        'PRIORIDAD' => 'Prioridades',
    ];

    /**
     * @param  list<array{id: string, llegada: int, rafaga: int, prioridad: int}>  $procesos
     * @return array<string, mixed>
     */
    public static function simular(string $algoritmo, array $procesos, int $quantum = 2): array
    {
        $procesos = self::normalizar($procesos);

        $gantt = match ($algoritmo) {
            'FCFS' => self::noExpropiativo($procesos, fn ($p) => [$p['llegada'], $p['orden']]),
            'SJF' => self::noExpropiativo($procesos, fn ($p) => [$p['rafaga'], $p['llegada'], $p['orden']]),
            'PRIORIDAD' => self::noExpropiativo($procesos, fn ($p) => [$p['prioridad'], $p['llegada'], $p['orden']]),
            'SRTF' => self::srtf($procesos),
            'RR' => self::roundRobin($procesos, max(1, $quantum)),
            default => throw new InvalidArgumentException("Algoritmo desconocido: {$algoritmo}"),
        };

        return self::resultados($algoritmo, $procesos, self::unir($gantt), $algoritmo === 'RR' ? $quantum : null);
    }

    /**
     * Los cinco algoritmos con los mismos procesos (tabla comparativa).
     *
     * @return array<string, array{espera: float, retorno: float}>
     */
    public static function comparar(array $procesos, int $quantum = 2): array
    {
        $tabla = [];
        foreach (array_keys(self::ALGORITMOS) as $alg) {
            $r = self::simular($alg, $procesos, $quantum);
            $tabla[$alg] = ['espera' => $r['espera_promedio'], 'retorno' => $r['retorno_promedio']];
        }

        return $tabla;
    }

    /**
     * FCFS, SJF y Prioridades: cuando la CPU queda libre se elige, entre los que ya llegaron,
     * el de menor clave y se ejecuta completo.
     */
    private static function noExpropiativo(array $procesos, callable $clave): array
    {
        $pendientes = $procesos;
        $tiempo = 0;
        $gantt = [];

        while ($pendientes) {
            $listos = array_filter($pendientes, fn ($p) => $p['llegada'] <= $tiempo);
            if (! $listos) {
                // CPU ociosa hasta la próxima llegada
                $proxima = min(array_column($pendientes, 'llegada'));
                $gantt[] = ['proceso' => '—', 'inicio' => $tiempo, 'fin' => $proxima];
                $tiempo = $proxima;

                continue;
            }
            usort($listos, fn ($a, $b) => $clave($a) <=> $clave($b));
            $elegido = $listos[0];
            $gantt[] = ['proceso' => $elegido['id'], 'inicio' => $tiempo, 'fin' => $tiempo + $elegido['rafaga']];
            $tiempo += $elegido['rafaga'];
            unset($pendientes[$elegido['orden']]);
        }

        return $gantt;
    }

    /** SRTF: en cada unidad de tiempo corre el proceso con menor tiempo restante. */
    private static function srtf(array $procesos): array
    {
        $restante = array_column($procesos, 'rafaga', 'orden');
        $tiempo = 0;
        $gantt = [];
        $actual = null;

        while (array_sum($restante) > 0) {
            $listos = array_filter($procesos, fn ($p) => $p['llegada'] <= $tiempo && $restante[$p['orden']] > 0);
            if (! $listos) {
                $gantt[] = ['proceso' => '—', 'inicio' => $tiempo, 'fin' => $tiempo + 1];
                $tiempo++;

                continue;
            }
            usort($listos, function ($a, $b) use ($restante, $actual) {
                // Empate en tiempo restante: se queda el que ya está en CPU, luego el que llegó primero
                return [$restante[$a['orden']], $a['orden'] === $actual ? 0 : 1, $a['llegada'], $a['orden']]
                    <=> [$restante[$b['orden']], $b['orden'] === $actual ? 0 : 1, $b['llegada'], $b['orden']];
            });
            $elegido = $listos[0];
            $actual = $elegido['orden'];
            $gantt[] = ['proceso' => $elegido['id'], 'inicio' => $tiempo, 'fin' => $tiempo + 1];
            $restante[$actual]--;
            $tiempo++;
        }

        return $gantt;
    }

    /** Round Robin con quantum fijo y cola FIFO. */
    private static function roundRobin(array $procesos, int $quantum): array
    {
        $porLlegada = $procesos;
        usort($porLlegada, fn ($a, $b) => [$a['llegada'], $a['orden']] <=> [$b['llegada'], $b['orden']]);

        $restante = array_column($procesos, 'rafaga', 'orden');
        $cola = [];
        $siguiente = 0; // índice del próximo proceso por llegar
        $tiempo = 0;
        $gantt = [];
        $total = count($porLlegada);

        $admitir = function (int $hasta) use (&$cola, &$siguiente, $porLlegada, $total) {
            while ($siguiente < $total && $porLlegada[$siguiente]['llegada'] <= $hasta) {
                $cola[] = $porLlegada[$siguiente]['orden'];
                $siguiente++;
            }
        };

        $admitir($tiempo);
        while ($cola || $siguiente < $total) {
            if (! $cola) {
                $proxima = $porLlegada[$siguiente]['llegada'];
                $gantt[] = ['proceso' => '—', 'inicio' => $tiempo, 'fin' => $proxima];
                $tiempo = $proxima;
                $admitir($tiempo);

                continue;
            }
            $orden = array_shift($cola);
            $corre = min($quantum, $restante[$orden]);
            $gantt[] = ['proceso' => $procesos[$orden]['id'], 'inicio' => $tiempo, 'fin' => $tiempo + $corre];
            $tiempo += $corre;
            $restante[$orden] -= $corre;

            $admitir($tiempo); // primero los que llegaron durante el quantum
            if ($restante[$orden] > 0) {
                $cola[] = $orden; // después el expropiado
            }
        }

        return $gantt;
    }

    /** Junta tramos consecutivos del mismo proceso (SRTF avanza de 1 en 1). */
    private static function unir(array $gantt): array
    {
        $unido = [];
        foreach ($gantt as $tramo) {
            $ultimo = array_key_last($unido);
            if ($ultimo !== null && $unido[$ultimo]['proceso'] === $tramo['proceso'] && $unido[$ultimo]['fin'] === $tramo['inicio']) {
                $unido[$ultimo]['fin'] = $tramo['fin'];
            } else {
                $unido[] = $tramo;
            }
        }

        return $unido;
    }

    private static function resultados(string $algoritmo, array $procesos, array $gantt, ?int $quantum): array
    {
        $fin = [];
        foreach ($gantt as $tramo) {
            $fin[$tramo['proceso']] = $tramo['fin']; // el último tramo de cada proceso es su fin
        }

        $filas = [];
        foreach ($procesos as $p) {
            $retorno = $fin[$p['id']] - $p['llegada'];
            $filas[] = [
                'id' => $p['id'],
                'llegada' => $p['llegada'],
                'rafaga' => $p['rafaga'],
                'prioridad' => $p['prioridad'],
                'fin' => $fin[$p['id']],
                'retorno' => $retorno,
                'espera' => $retorno - $p['rafaga'],
            ];
        }

        $n = count($filas);

        return [
            'algoritmo' => $algoritmo,
            'quantum' => $quantum,
            'gantt' => $gantt,
            'resultados' => $filas,
            'espera_promedio' => round(array_sum(array_column($filas, 'espera')) / $n, 2),
            'retorno_promedio' => round(array_sum(array_column($filas, 'retorno')) / $n, 2),
        ];
    }

    /**
     * Valida la entrada y agrega 'orden' (posición original, usada para desempates).
     */
    private static function normalizar(array $procesos): array
    {
        if (count($procesos) < 1 || count($procesos) > 15) {
            throw new InvalidArgumentException('Se necesitan entre 1 y 15 procesos.');
        }

        $limpios = [];
        foreach (array_values($procesos) as $i => $p) {
            $llegada = filter_var($p['llegada'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
            $rafaga = filter_var($p['rafaga'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            $prioridad = filter_var($p['prioridad'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
            if ($llegada === false || $rafaga === false || $prioridad === false) {
                throw new InvalidArgumentException('P'.($i + 1).': llegada 0–100, ráfaga 1–100 y prioridad 0–100 (enteros).');
            }
            $limpios[$i] = [
                'id' => 'P'.($i + 1),
                'llegada' => $llegada,
                'rafaga' => $rafaga,
                'prioridad' => $prioridad,
                'orden' => $i,
            ];
        }

        return $limpios;
    }
}
