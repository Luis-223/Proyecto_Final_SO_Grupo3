<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Services\Sistema\ProcReader;
use Illuminate\View\View;

/**
 * M6 — Dashboard con indicadores principales (leídos de /proc).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $mem = ProcReader::memoria();
        $total = $mem['MemTotal'] ?? 0;
        $usada = $total - ($mem['MemAvailable'] ?? 0);

        return view('dashboard', [
            'carga' => ProcReader::cargaPromedio(),
            'uptime' => ProcReader::uptime(),
            'memTotalMb' => intdiv($total, 1024),
            'memUsadaPct' => $total ? round($usada * 100 / $total, 1) : 0,
            'procesos' => count(ProcReader::pids()),
            'ultimas' => Bitacora::with('usuario')->latest('created_at')->limit(5)->get(),
        ]);
    }
}
