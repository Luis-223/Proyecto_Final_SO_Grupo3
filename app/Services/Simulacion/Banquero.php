<?php

namespace App\Services\Simulacion;

use InvalidArgumentException;

/**
 * M4 — Algoritmo del banquero, evaluación de solicitudes y grafo de asignación de recursos.
 *
 * Notación (Silberschatz):
 *   Asignación[i][j]  instancias de Rj que tiene Pi
 *   Máximo[i][j]      máximo que Pi puede pedir de Rj
 *   Necesidad = Máximo − Asignación
 *   Disponible[j]     instancias libres de Rj
 */
final class Banquero
{
    /**
     * Valida las matrices y calcula Necesidad.
     *
     * @return array{n: int, m: int, asignacion: array, maximo: array, disponible: array, necesidad: array}
     */
    public static function preparar(array $asignacion, array $maximo, array $disponible): array
    {
        $n = count($asignacion);
        $m = count($disponible);
        if ($n < 1 || $n > 10 || $m < 1 || $m > 6 || count($maximo) !== $n) {
            throw new InvalidArgumentException('Se aceptan de 1 a 10 procesos y de 1 a 6 tipos de recurso.');
        }

        $entero = fn ($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
        $A = $M = $N = [];
        $D = [];
        for ($j = 0; $j < $m; $j++) {
            $D[$j] = $entero($disponible[$j] ?? null);
            if ($D[$j] === false) {
                throw new InvalidArgumentException('Disponible: use enteros de 0 a 99.');
            }
        }
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                $a = $entero($asignacion[$i][$j] ?? null);
                $x = $entero($maximo[$i][$j] ?? null);
                if ($a === false || $x === false) {
                    throw new InvalidArgumentException("P{$i}: Asignación y Máximo deben ser enteros de 0 a 99.");
                }
                if ($a > $x) {
                    throw new InvalidArgumentException("P{$i} tiene asignado más de su máximo en ".self::recurso($j).'.');
                }
                $A[$i][$j] = $a;
                $M[$i][$j] = $x;
                $N[$i][$j] = $x - $a;
            }
        }

        return ['n' => $n, 'm' => $m, 'asignacion' => $A, 'maximo' => $M, 'disponible' => $D, 'necesidad' => $N];
    }

    /**
     * Algoritmo de seguridad con el registro de cada comprobación.
     * Recorre P0..Pn-1 en pasadas sucesivas; cada vez que un proceso puede terminar
     * libera su asignación (Trabajo += Asignación[i]) y se sigue con el siguiente.
     *
     * @return array{seguro: bool, secuencia: list<string>, pasos: list<array>, bloqueados: list<int>}
     */
    public static function seguridad(array $estado): array
    {
        ['n' => $n, 'm' => $m, 'asignacion' => $A, 'necesidad' => $N] = $estado;
        $trabajo = $estado['disponible'];
        $terminado = array_fill(0, $n, false);
        $secuencia = [];
        $pasos = [];
        $paso = 0;

        do {
            $avanzo = false;
            for ($i = 0; $i < $n; $i++) {
                if ($terminado[$i]) {
                    continue;
                }
                $cabe = self::menorIgual($N[$i], $trabajo);
                $antes = $trabajo;
                if ($cabe) {
                    for ($j = 0; $j < $m; $j++) {
                        $trabajo[$j] += $A[$i][$j];
                    }
                    $terminado[$i] = true;
                    $secuencia[] = "P{$i}";
                    $avanzo = true;
                }
                $pasos[] = [
                    'paso' => ++$paso,
                    'proceso' => "P{$i}",
                    'necesidad' => $N[$i],
                    'trabajo' => $antes,
                    'cabe' => $cabe,
                    'trabajo_nuevo' => $cabe ? $trabajo : null,
                ];
            }
        } while ($avanzo && in_array(false, $terminado, true));

        return [
            'seguro' => ! in_array(false, $terminado, true),
            'secuencia' => $secuencia,
            'pasos' => $pasos,
            'bloqueados' => array_keys(array_filter($terminado, fn ($t) => ! $t)),
        ];
    }

    /**
     * Algoritmo de solicitud de recursos de Pi.
     *
     * @return array{concedida: bool, motivo: string, seguridad: ?array, estado: ?array}
     */
    public static function solicitud(array $estado, int $i, array $pedido): array
    {
        if ($i < 0 || $i >= $estado['n']) {
            throw new InvalidArgumentException('Proceso inválido.');
        }
        $m = $estado['m'];
        $req = [];
        for ($j = 0; $j < $m; $j++) {
            $req[$j] = filter_var($pedido[$j] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            if ($req[$j] === false) {
                throw new InvalidArgumentException('La solicitud debe tener enteros de 0 a 99.');
            }
        }

        if (! self::menorIgual($req, $estado['necesidad'][$i])) {
            return ['concedida' => false, 'motivo' => "Error: P{$i} pide más de lo que declaró como máximo (Solicitud > Necesidad).", 'seguridad' => null, 'estado' => null];
        }
        if (! self::menorIgual($req, $estado['disponible'])) {
            return ['concedida' => false, 'motivo' => "P{$i} debe esperar: no hay suficientes recursos disponibles (Solicitud > Disponible).", 'seguridad' => null, 'estado' => null];
        }

        // Se simula la asignación y se revisa si el nuevo estado es seguro
        $nuevo = $estado;
        for ($j = 0; $j < $m; $j++) {
            $nuevo['disponible'][$j] -= $req[$j];
            $nuevo['asignacion'][$i][$j] += $req[$j];
            $nuevo['necesidad'][$i][$j] -= $req[$j];
        }
        $seg = self::seguridad($nuevo);

        return [
            'concedida' => $seg['seguro'],
            'motivo' => $seg['seguro']
                ? "Se concede: el nuevo estado es seguro con la secuencia ⟨".implode(', ', $seg['secuencia']).'⟩.'
                : 'Se niega: al asignarla el sistema quedaría en estado inseguro. P'.$i.' debe esperar.',
            'seguridad' => $seg,
            'estado' => $nuevo,
        ];
    }

    /**
     * Grafo de asignación de recursos:
     *   R → P  (asignación): Asignación[i][j] > 0
     *   P → R  (solicitud):  Necesidad[i][j] > Disponible[j], es decir, Pi quedaría bloqueado esperando Rj.
     * Se buscan ciclos con DFS. Con varias instancias por recurso un ciclo es condición
     * necesaria pero no suficiente: por eso el interbloqueo real se confirma con el algoritmo de detección.
     *
     * @return array{nodos: list<array>, aristas: list<array>, ciclos: list<list<string>>}
     */
    public static function grafo(array $estado): array
    {
        ['n' => $n, 'm' => $m, 'asignacion' => $A, 'necesidad' => $N, 'disponible' => $D] = $estado;
        $nodos = [];
        $aristas = [];
        $ady = [];

        for ($i = 0; $i < $n; $i++) {
            $nodos[] = ['id' => "P{$i}", 'tipo' => 'proceso'];
        }
        for ($j = 0; $j < $m; $j++) {
            $nodos[] = ['id' => self::recurso($j), 'tipo' => 'recurso', 'instancias' => $D[$j] + array_sum(array_column($A, $j))];
        }
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                $p = "P{$i}";
                $r = self::recurso($j);
                if ($A[$i][$j] > 0) {
                    $aristas[] = ['de' => $r, 'a' => $p, 'tipo' => 'asignacion', 'cantidad' => $A[$i][$j]];
                    $ady[$r][] = $p;
                }
                if ($N[$i][$j] > $D[$j]) {
                    $aristas[] = ['de' => $p, 'a' => $r, 'tipo' => 'solicitud', 'cantidad' => $N[$i][$j]];
                    $ady[$p][] = $r;
                }
            }
        }

        return ['nodos' => $nodos, 'aristas' => $aristas, 'ciclos' => self::ciclos($ady)];
    }

    /**
     * Estrategias de recuperación para los procesos interbloqueados.
     * Víctima: el proceso que, al liberar sus recursos, permite terminar a más procesos;
     * si empatan, el que tiene menos recursos asignados (menor costo de perder su trabajo).
     *
     * @return array{terminacion: string, apropiacion: string}
     */
    public static function recuperacion(array $estado, array $bloqueados): array
    {
        if (! $bloqueados) {
            return [
                'terminacion' => 'No es necesaria: todos los procesos pueden terminar.',
                'apropiacion' => 'No es necesaria: el estado es seguro.',
            ];
        }

        $mejor = null;
        foreach ($bloqueados as $v) {
            $prueba = $estado;
            for ($j = 0; $j < $estado['m']; $j++) {
                $prueba['disponible'][$j] += $estado['asignacion'][$v][$j];
                $prueba['asignacion'][$v][$j] = 0;
                $prueba['necesidad'][$v][$j] = 0; // la víctima ya no pide nada
            }
            $seg = self::seguridad($prueba);
            $restantes = count(array_diff($seg['bloqueados'], [$v]));
            $costo = array_sum($estado['asignacion'][$v]);
            $clave = [$restantes, $costo, $v];
            if ($mejor === null || $clave < $mejor['clave']) {
                $mejor = ['clave' => $clave, 'victima' => $v, 'seguridad' => $seg, 'restantes' => $restantes];
            }
        }

        $v = $mejor['victima'];
        $libera = self::vectorTexto($estado['asignacion'][$v]);
        $despues = $mejor['restantes'] === 0
            ? 'el resto puede terminar con la secuencia ⟨'.implode(', ', array_diff($mejor['seguridad']['secuencia'], ["P{$v}"])).'⟩'
            : "aún quedarían {$mejor['restantes']} procesos bloqueados y habría que elegir otra víctima";

        // Para la apropiación: a quién le sirven los recursos quitados
        $beneficiado = null;
        foreach ($bloqueados as $i) {
            if ($i === $v) {
                continue;
            }
            $conLoQuitado = array_map(fn ($d, $a) => $d + $a, $estado['disponible'], $estado['asignacion'][$v]);
            if (self::menorIgual($estado['necesidad'][$i], $conLoQuitado)) {
                $beneficiado = $i;
                break;
            }
        }

        return [
            'terminacion' => "Abortar P{$v}, que libera {$libera}; {$despues}. Se elige porque desbloquea a más procesos con el menor costo.",
            'apropiacion' => "Quitar a P{$v} sus recursos ({$libera}) y regresarlo a un punto de control (rollback)"
                .($beneficiado !== null ? "; con ellos P{$beneficiado} puede completar su Necesidad y terminar." : '; luego se reasignan a los procesos bloqueados.'),
        ];
    }

    /** Ejecuta todo el análisis de un estado. */
    public static function evaluar(array $asignacion, array $maximo, array $disponible): array
    {
        $estado = self::preparar($asignacion, $maximo, $disponible);
        $seg = self::seguridad($estado);

        return [
            'estado' => $estado,
            'seguridad' => $seg,
            'grafo' => self::grafo($estado),
            'recuperacion' => self::recuperacion($estado, $seg['bloqueados']),
        ];
    }

    public static function recurso(int $j): string
    {
        return chr(ord('A') + $j);
    }

    private static function menorIgual(array $a, array $b): bool
    {
        foreach ($a as $j => $v) {
            if ($v > $b[$j]) {
                return false;
            }
        }

        return true;
    }

    private static function vectorTexto(array $v): string
    {
        return implode(', ', array_map(fn ($c, $j) => self::recurso($j).'='.$c, $v, array_keys($v)));
    }

    /**
     * Ciclos simples del grafo dirigido (DFS con pila). Se normalizan para no repetir
     * el mismo ciclo empezando en otro nodo. Máximo 10.
     *
     * @param  array<string, list<string>>  $ady
     * @return list<list<string>>
     */
    private static function ciclos(array $ady): array
    {
        $encontrados = [];
        $nodos = array_keys($ady);
        sort($nodos);

        foreach ($nodos as $inicio) {
            $pila = [[$inicio, [$inicio]]];
            while ($pila && count($encontrados) < 10) {
                [$nodo, $camino] = array_pop($pila);
                foreach ($ady[$nodo] ?? [] as $sig) {
                    if ($sig === $inicio) {
                        $clave = self::normalizarCiclo($camino);
                        $encontrados[implode('→', $clave)] = $clave;
                    } elseif (! in_array($sig, $camino, true) && strcmp($sig, $inicio) > 0 && count($camino) < 16) {
                        // Solo nodos "mayores" que el inicio: cada ciclo se encuentra desde su nodo menor
                        $pila[] = [$sig, [...$camino, $sig]];
                    }
                }
            }
        }

        return array_values($encontrados);
    }

    private static function normalizarCiclo(array $camino): array
    {
        // Se empieza por el proceso de menor número (P0 → A → P1 → ...) para que se lea mejor
        $procesos = array_filter($camino, fn ($n) => str_starts_with($n, 'P'));
        usort($procesos, fn ($a, $b) => (int) substr($a, 1) <=> (int) substr($b, 1));
        $min = array_search($procesos[0] ?? min($camino), $camino, true);

        return [...array_slice($camino, $min), ...array_slice($camino, 0, $min)];
    }
}
