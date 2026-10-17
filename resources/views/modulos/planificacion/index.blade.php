@extends('layouts.app')

@section('titulo', 'Planificación de CPU')
@section('modulo', 'M3 · simulación')

@section('contenido')
@php $esAdmin = auth()->user()->esAdministrador(); @endphp

<div id="m3"
     data-url-simular="{{ route('planificacion.simular') }}"
     data-url-guardar="{{ $esAdmin ? route('planificacion.guardar') : '' }}"
     data-url-lista="{{ route('planificacion.lista') }}"
     data-url-base="{{ url('planificacion/simulaciones') }}"
     data-algoritmos='@json($algoritmos)'>

<div class="d-flex justify-content-end gap-2 mb-3">
    <button type="button" class="btn btn-outline-secondary" id="ver-guardadas"><i class="bi bi-clock-history"></i> Simulaciones guardadas</button>
    @if ($esAdmin)
        <button type="button" class="btn btn-sm-accent" id="guardar" disabled><i class="bi bi-save"></i> Guardar simulación</button>
    @else
        <span class="d-inline-block" tabindex="0" title="Solo el rol Administrador puede guardar">
            <button type="button" class="btn btn-sm-accent" disabled><i class="bi bi-save"></i> Guardar simulación</button>
        </span>
    @endif
</div>

<div class="row g-3 mb-3">
    {{-- Procesos de entrada --}}
    <div class="col-xl-8"><div class="sm-card h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Procesos</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="agregar"><i class="bi bi-plus-lg"></i> Agregar</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="aleatorio"><i class="bi bi-shuffle"></i> Generar aleatorio</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ejemplo" title="P1–P5 del caso de prueba del grupo">Ejemplo</button>
            </div>
        </div>
        <table class="table align-middle mb-2">
            <thead>
                <tr class="text-secondary" style="font-size: 12px; text-transform: uppercase">
                    <th style="width: 90px">Proceso</th><th>Llegada</th><th>Ráfaga CPU</th><th>Prioridad</th><th style="width: 50px"></th>
                </tr>
            </thead>
            <tbody id="filas-procesos"></tbody>
        </table>
        <small class="text-secondary">Prioridad: número menor = mayor prioridad. Máximo 15 procesos; valores enteros de 0 a 100.</small>
    </div></div>

    {{-- Algoritmo --}}
    <div class="col-xl-4"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Algoritmo</h2>
        <div class="row g-2">
            @foreach ($algoritmos as $valor => $nombre)
                <div class="col-6">
                    <input type="radio" class="btn-check" name="algoritmo" id="alg-{{ $valor }}" value="{{ $valor }}" @checked($loop->first)>
                    <label class="btn btn-outline-secondary w-100 text-start" for="alg-{{ $valor }}">{{ $nombre }}</label>
                </div>
            @endforeach
        </div>
        <div class="d-flex align-items-center gap-2">
            <label for="quantum" class="fw-semibold" style="font-size: 14px">Quantum</label>
            <input id="quantum" type="number" min="1" max="20" value="2" class="form-control sm-mono" style="width: 90px">
            <small class="text-secondary">solo Round Robin</small>
        </div>
        <div class="text-secondary" style="font-size: 12px" id="descripcion-algoritmo"></div>
        <button type="button" class="btn btn-sm-dark btn-lg mt-auto" id="simular">Simular</button>
    </div></div>
</div>

{{-- Diagrama de Gantt --}}
<div class="sm-card mb-3">
    <div class="d-flex justify-content-between align-items-baseline">
        <h2>Diagrama de Gantt</h2>
        <span class="sm-fuente" id="titulo-gantt"></span>
    </div>
    <div id="gantt" class="sm-area-vacia" style="min-height: 90px">Ingrese los procesos y presione “Simular” para ver el diagrama.</div>
</div>

<div class="row g-3">
    {{-- Resultados por proceso --}}
    <div class="col-xl-6">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head"><h2 class="mb-0">Resultados por proceso</h2></div>
            <table class="table align-middle">
                <thead><tr><th>Proceso</th><th>Llegada</th><th>Ráfaga</th><th>Fin</th><th>Retorno</th><th>Espera</th></tr></thead>
                <tbody id="resultados">
                    <tr><td colspan="6" class="sm-vacio">Sin resultados todavía.</td></tr>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold"><td>Promedio</td><td></td><td></td><td></td><td class="sm-num" id="retorno-promedio">—</td><td class="sm-num" id="espera-promedio">—</td></tr>
                </tfoot>
            </table>
            <div class="px-3 pb-3 sm-fuente">retorno = fin − llegada · espera = retorno − ráfaga</div>
        </div>
    </div>

    {{-- Comparativa --}}
    <div class="col-xl-6">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head"><h2 class="mb-0">Comparativa de algoritmos (mismos procesos)</h2></div>
            <table class="table align-middle">
                <thead><tr><th>Algoritmo</th><th class="text-end">Espera promedio</th><th class="text-end">Retorno promedio</th></tr></thead>
                <tbody id="comparativa">
                    @foreach ($algoritmos as $valor => $nombre)
                        <tr data-alg="{{ $valor }}"><td>{{ $nombre }}</td><td class="text-end sm-num">—</td><td class="text-end sm-num">—</td></tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-3 pb-3"><div style="height: 180px"><canvas id="grafica-comparativa"></canvas></div></div>
        </div>
    </div>
</div>

{{-- Simulaciones guardadas --}}
<div class="modal fade" id="modal-guardadas" tabindex="-1" aria-labelledby="titulo-guardadas" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titulo-guardadas">Simulaciones guardadas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>#</th><th>Nombre</th><th>Algoritmo</th><th>Proc.</th><th class="text-end">Espera</th><th class="text-end">Retorno</th><th>Usuario</th><th>Fecha</th></tr></thead>
                    <tbody id="lista-guardadas"><tr><td colspan="8" class="sm-vacio">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('estilos')
<style>
    .sm-gantt { width: 100%; }
    .sm-gantt-barra { display: flex; height: 44px; border-radius: 6px; overflow: hidden; border: 1px solid #cfd5cf; }
    .sm-gantt-tramo { display: flex; align-items: center; justify-content: center; color: #fff; font-family: var(--sm-mono); font-size: 13px; font-weight: 500; border-right: 1px solid rgba(255,255,255,.6); min-width: 0; overflow: hidden; }
    .sm-gantt-tramo.ocioso { background: repeating-linear-gradient(45deg, #eef0ed, #eef0ed 6px, #e2e5e1 6px, #e2e5e1 12px); color: #5f6965; }
    .sm-gantt-eje { position: relative; height: 22px; font-family: var(--sm-mono); font-size: 11px; color: #5f6965; }
    .sm-gantt-eje span { position: absolute; transform: translateX(-50%); top: 3px; }
    #lista-guardadas tr { cursor: pointer; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/m3-planificacion.js') }}?v=1"></script>
@endpush
