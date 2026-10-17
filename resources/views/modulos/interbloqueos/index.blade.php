@extends('layouts.app')

@section('titulo', 'Interbloqueos')
@section('modulo', 'M4 · algoritmo del banquero · grafo de asignación')

@section('contenido')
<div id="m4" data-url-evaluar="{{ route('interbloqueos.evaluar') }}" data-url-solicitud="{{ route('interbloqueos.solicitud') }}">

{{-- Dimensiones --}}
<div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3" style="font-size: 14px">
    <label for="n">Procesos (n)</label>
    <input id="n" type="number" min="1" max="10" value="5" class="form-control sm-mono" style="width: 80px">
    <label for="m" class="ms-2">Recursos (m)</label>
    <input id="m" type="number" min="1" max="6" value="3" class="form-control sm-mono" style="width: 80px">
    <button type="button" class="btn btn-outline-secondary ms-2" id="ejemplo-seguro" title="Silberschatz: 5 procesos, A=10 B=5 C=7">Ejemplo seguro</button>
    <button type="button" class="btn btn-outline-secondary" id="ejemplo-bloqueo" title="3 procesos en espera circular">Ejemplo con interbloqueo</button>
    <button type="button" class="btn btn-sm-dark" id="evaluar">Evaluar estado</button>
</div>

{{-- Matrices (las arma m4-interbloqueos.js según n y m) --}}
<div class="sm-card mb-3">
    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="d-flex justify-content-between align-items-baseline mb-2"><h2 class="mb-0">Asignación</h2><small class="text-secondary">entrada</small></div>
            <div class="sm-matriz" id="matriz-asignacion"></div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="d-flex justify-content-between align-items-baseline mb-2"><h2 class="mb-0">Máximo</h2><small class="text-secondary">entrada</small></div>
            <div class="sm-matriz" id="matriz-maximo"></div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="d-flex justify-content-between align-items-baseline mb-2"><h2 class="mb-0">Necesidad</h2><small class="text-secondary">Máximo − Asignación</small></div>
            <div class="sm-matriz" id="matriz-necesidad"></div>
        </div>
        <div class="col-md-6 col-xl-3 d-flex flex-column gap-3">
            <div>
                <h2 class="mb-2">Disponible</h2>
                <div class="sm-matriz" id="vector-disponible"></div>
                <div class="sm-fuente mt-2" id="totales">Total por recurso: —</div>
            </div>
            <div id="estado-sistema" class="p-3 rounded" style="background: #f7f8f6; font-size: 14px; color: #3d4643">
                <div class="fw-semibold">Estado del sistema</div>
                <div class="sm-mono" id="estado-texto">—</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Paso a paso --}}
    <div class="col-xl-7">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head"><h2 class="mb-0">Paso a paso del algoritmo de seguridad</h2></div>
            <div style="max-height: 420px; overflow: auto">
                <table class="table align-middle">
                    <thead class="sticky-top"><tr><th>Paso</th><th>Proceso</th><th>¿Necesidad ≤ Trabajo?</th><th>Trabajo nuevo</th><th>Resultado</th></tr></thead>
                    <tbody id="pasos">
                        <tr><td colspan="5" class="sm-vacio">Ingrese las matrices y presione “Evaluar estado”.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="px-3 pb-3 pt-2 sm-fuente">Trabajo inicia en Disponible. Si Necesidad[i] ≤ Trabajo, Pi termina y Trabajo += Asignación[i].</div>
        </div>
    </div>

    {{-- Solicitud de recursos --}}
    <div class="col-xl-5"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Evaluar solicitud de recursos</h2>
        <div class="d-flex flex-wrap align-items-center gap-2" style="font-size: 14px">
            <label for="proceso-solicitud">Proceso</label>
            <select id="proceso-solicitud" class="form-select sm-mono" style="width: auto"></select>
            <span class="ms-1">solicita</span>
            <span id="pedido" class="d-flex gap-2"></span>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="evaluar-solicitud">Evaluar solicitud</button>
        <div id="resultado-solicitud" class="sm-area-vacia mt-auto" style="min-height: 90px">El resultado de la solicitud aparecerá aquí.</div>
    </div></div>
</div>

<div class="row g-3">
    {{-- Grafo --}}
    <div class="col-xl-7"><div class="sm-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
            <h2 class="mb-0">Grafo de asignación de recursos</h2>
            <small class="text-secondary">○ proceso · □ recurso · R → P asignado · P ⇢ R espera (Necesidad &gt; Disponible)</small>
        </div>
        <div id="grafo" class="sm-area-vacia" style="min-height: 240px">El grafo se dibuja al evaluar el estado.</div>
    </div></div>

    {{-- Detección y recuperación --}}
    <div class="col-xl-5"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Detección y recuperación</h2>
        <div class="p-3 rounded" style="background: #f7f8f6; font-size: 14px">
            <div class="fw-semibold">Ciclos detectados</div>
            <div class="sm-mono" id="ciclos">—</div>
            <div class="mt-2" style="font-size: 12px; color: #5f6965" id="nota-ciclos"></div>
        </div>
        <div>
            <div class="fw-semibold mb-2" style="font-size: 14px">Estrategias de recuperación</div>
            <div class="border rounded p-2 mb-2" style="font-size: 13px"><strong>Terminación:</strong> <span id="sugerencia-terminacion">—</span></div>
            <div class="border rounded p-2" style="font-size: 13px"><strong>Apropiación:</strong> <span id="sugerencia-apropiacion">—</span></div>
        </div>
    </div></div>
</div>
</div>
@endsection

@push('estilos')
<style>
    #pasos tr.paso-si td { background: #eef7f2; }
    #grafo svg { width: 100%; height: auto; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/m4-interbloqueos.js') }}?v=1"></script>
@endpush
