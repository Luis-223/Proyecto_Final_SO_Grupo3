<?php

namespace Tests\Unit;

use App\Services\Simulacion\Planificador;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * M3 — Caso de prueba del grupo (docs/mockups/README.md):
 * P1–P5 con (llegada, ráfaga, prioridad) = (0,5,3) (1,3,1) (2,8,4) (3,6,2) (4,2,5).
 * Ejecutar con: php artisan test --filter=PlanificadorTest
 */
class PlanificadorTest extends TestCase
{
    private function procesos(): array
    {
        return array_map(
            fn ($p) => ['llegada' => $p[0], 'rafaga' => $p[1], 'prioridad' => $p[2]],
            [[0, 5, 3], [1, 3, 1], [2, 8, 4], [3, 6, 2], [4, 2, 5]]
        );
    }

    public function test_espera_promedio_de_los_cinco_algoritmos(): void
    {
        $tabla = Planificador::comparar($this->procesos(), 2);

        $this->assertSame(8.2, $tabla['FCFS']['espera']);
        $this->assertSame(5.6, $tabla['SJF']['espera']);
        $this->assertSame(5.2, $tabla['SRTF']['espera']);
        $this->assertSame(10.6, $tabla['RR']['espera']);
        $this->assertSame(7.8, $tabla['PRIORIDAD']['espera']);
    }

    public function test_gantt_de_srtf(): void
    {
        $gantt = Planificador::simular('SRTF', $this->procesos())['gantt'];
        $texto = implode(' ', array_map(fn ($g) => "{$g['proceso']}[{$g['inicio']}-{$g['fin']}]", $gantt));

        $this->assertSame('P1[0-1] P2[1-4] P5[4-6] P1[6-10] P4[10-16] P3[16-24]', $texto);
    }

    public function test_cpu_ociosa_cuando_nadie_ha_llegado(): void
    {
        $r = Planificador::simular('FCFS', [['llegada' => 3, 'rafaga' => 2, 'prioridad' => 0]]);

        $this->assertSame('—', $r['gantt'][0]['proceso']);
        $this->assertSame(5, $r['resultados'][0]['fin']);
        $this->assertSame(0, $r['resultados'][0]['espera']);
    }

    public function test_rechaza_entradas_invalidas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Planificador::simular('FCFS', [['llegada' => '1; rm -rf /', 'rafaga' => 2]]);
    }
}
