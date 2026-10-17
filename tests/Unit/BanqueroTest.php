<?php

namespace Tests\Unit;

use App\Services\Simulacion\Banquero;
use PHPUnit\Framework\TestCase;

/**
 * M4 — Ejemplo clásico de Silberschatz (5 procesos; A=10, B=5, C=7; Disponible 3,3,2).
 * Ejecutar con: php artisan test --filter=BanqueroTest
 */
class BanqueroTest extends TestCase
{
    private const ASIGNACION = [[0, 1, 0], [2, 0, 0], [3, 0, 2], [2, 1, 1], [0, 0, 2]];
    private const MAXIMO = [[7, 5, 3], [3, 2, 2], [9, 0, 2], [2, 2, 2], [4, 3, 3]];
    private const DISPONIBLE = [3, 3, 2];

    public function test_estado_seguro_y_secuencia(): void
    {
        $r = Banquero::evaluar(self::ASIGNACION, self::MAXIMO, self::DISPONIBLE);

        $this->assertTrue($r['seguridad']['seguro']);
        $this->assertSame(['P1', 'P3', 'P4', 'P0', 'P2'], $r['seguridad']['secuencia']);
        $this->assertSame([7, 4, 3], $r['estado']['necesidad'][0]);
    }

    public function test_solicitudes_del_libro(): void
    {
        $estado = Banquero::preparar(self::ASIGNACION, self::MAXIMO, self::DISPONIBLE);

        $p1 = Banquero::solicitud($estado, 1, [1, 0, 2]);
        $this->assertTrue($p1['concedida']);

        // Después de conceder a P1: P4 (3,3,0) debe esperar y P0 (0,2,0) deja el sistema inseguro
        $this->assertFalse(Banquero::solicitud($p1['estado'], 4, [3, 3, 0])['concedida']);
        $this->assertFalse(Banquero::solicitud($p1['estado'], 0, [0, 2, 0])['concedida']);
    }

    public function test_interbloqueo_circular_tiene_ciclo_y_victima(): void
    {
        $r = Banquero::evaluar([[1, 0, 0], [0, 1, 0], [0, 0, 1]], [[1, 1, 0], [0, 1, 1], [1, 0, 1]], [0, 0, 0]);

        $this->assertFalse($r['seguridad']['seguro']);
        $this->assertSame([['P0', 'B', 'P1', 'C', 'P2', 'A']], $r['grafo']['ciclos']);
        $this->assertStringContainsString('Abortar P0', $r['recuperacion']['terminacion']);
    }
}
