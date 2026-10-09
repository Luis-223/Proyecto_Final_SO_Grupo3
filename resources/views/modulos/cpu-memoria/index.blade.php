@extends('layouts.app')

@section('titulo', 'CPU y Memoria')
@section('modulo', 'M2 · /proc/cpuinfo · /proc/stat · /proc/meminfo · /proc/loadavg')

@section('contenido')
{{-- Controles de actualización --}}
<div class="d-flex justify-content-end align-items-center gap-2 mb-3" style="font-size: 14px">
    <label for="intervalo" class="text-secondary">Actualizar cada</label>
    <select id="intervalo" class="form-select form-select-sm" style="width: auto">
        <option value="2000">2 s</option>
        <option value="3000" selected>3 s</option>
        <option value="5000">5 s</option>
    </select>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="pausar"><i class="bi bi-pause-fill"></i> Pausar</button>
</div>

{{-- Indicadores --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6 col-xl-4"><div class="sm-card h-100">
        <div class="sm-kpi-label">Modelo de CPU</div>
        <div class="fw-semibold fs-6" id="cpu-modelo">—</div>
        <div class="sm-fuente" id="cpu-nucleos">— núcleos · /proc/cpuinfo</div>
    </div></div>
    <div class="col-6 col-lg-3 col-xl-2"><div class="sm-card h-100">
        <div class="sm-kpi-label">Uso de CPU</div>
        <div class="sm-kpi-value" id="cpu-uso">—</div>
        <div class="sm-fuente">/proc/stat</div>
    </div></div>
    <div class="col-6 col-lg-3 col-xl-3"><div class="sm-card h-100">
        <div class="sm-kpi-label">Carga 1 / 5 / 15 min</div>
        <div class="sm-kpi-value sm-mono" style="font-size: 24px" id="carga">—</div>
        <div class="sm-fuente">/proc/loadavg</div>
    </div></div>
    <div class="col-lg-6 col-xl-3"><div class="sm-card h-100">
        <div class="sm-kpi-label">Tiempo encendido</div>
        <div class="sm-kpi-value" id="uptime">—</div>
        <div class="sm-fuente">/proc/uptime</div>
    </div></div>
</div>

<div class="row g-3 mb-3">
    {{-- Gráfica de CPU --}}
    <div class="col-xl-7"><div class="sm-card h-100">
        <h2>CPU total y por núcleo (%)</h2>
        <div style="height: 240px"><canvas id="grafica-cpu"></canvas></div>
    </div></div>

    {{-- Memoria --}}
    <div class="col-xl-5"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Memoria</h2>
        <div>
            <div class="d-flex justify-content-between" style="font-size: 14px">
                <span class="fw-semibold">RAM · <span id="ram-total">—</span></span>
                <span class="sm-mono" id="ram-pct">—</span>
            </div>
            <div class="progress mt-2" style="height: 20px">
                <div class="progress-bar" id="ram-usada" style="width: 0%; background: #1a7a5c"></div>
                <div class="progress-bar" id="ram-cache" style="width: 0%; background: #9fcfbd"></div>
            </div>
            <div class="row mt-2" style="font-size: 13px">
                <div class="col-4"><span class="sm-leyenda" style="background: #1a7a5c"></span>Usada<div class="sm-num" id="ram-usada-mb">—</div></div>
                <div class="col-4"><span class="sm-leyenda" style="background: #9fcfbd"></span>Caché<div class="sm-num" id="ram-cache-mb">—</div></div>
                <div class="col-4"><span class="sm-leyenda" style="background: #e8ebe7; border: 1px solid #c9cfc9"></span>Libre<div class="sm-num" id="ram-libre-mb">—</div></div>
            </div>
        </div>
        <div>
            <div class="d-flex justify-content-between" style="font-size: 14px">
                <span class="fw-semibold">Swap · <span id="swap-total">—</span></span>
                <span class="sm-mono" id="swap-pct">—</span>
            </div>
            <div class="progress mt-2" style="height: 20px">
                <div class="progress-bar" id="swap-usada" style="width: 0%; background: #6a4fa3"></div>
            </div>
        </div>
    </div></div>
</div>

{{-- Top 5 --}}
<div class="row g-3">
    @foreach (['cpu' => 'Top 5 por CPU', 'memoria' => 'Top 5 por memoria'] as $clave => $titulo)
        <div class="col-xl-6">
            <div class="sm-card sm-card-tabla">
                <div class="sm-card-head"><h2 class="mb-0">{{ $titulo }}</h2></div>
                <table class="table align-middle">
                    <thead><tr><th>PID</th><th>Comando</th><th class="text-end">{{ $clave === 'cpu' ? '% CPU' : 'Memoria' }}</th></tr></thead>
                    <tbody id="top-{{ $clave }}">
                        <tr><td colspan="3" class="sm-vacio">No hay datos para mostrar.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
    // Gráfica vacía; el módulo M2 le agrega los datos con fetch cada 2–5 s.
    window.graficaCpu = new Chart(document.getElementById('grafica-cpu'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Total', data: [], borderColor: '#1a7a5c', borderWidth: 2.5, pointRadius: 0, tension: 0.3 },
                { label: 'cpu0', data: [], borderColor: '#2f6fa8', borderWidth: 1.5, pointRadius: 0, tension: 0.3 },
                { label: 'cpu1', data: [], borderColor: '#c7801a', borderWidth: 1.5, pointRadius: 0, tension: 0.3 },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { min: 0, max: 100, ticks: { callback: (v) => v + ' %' } } },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 14, boxHeight: 2 } } },
        },
    });
</script>
@endpush
