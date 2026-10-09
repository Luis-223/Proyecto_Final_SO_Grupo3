@extends('layouts.app')

@section('titulo', 'Planificación de CPU')
@section('modulo', 'M3 · simulación')

@section('contenido')
@php
    $algoritmos = ['FCFS' => 'FCFS', 'SJF' => 'SJF', 'SRTF' => 'SRTF', 'RR' => 'Round Robin', 'PRIORIDAD' => 'Prioridades'];
@endphp

<div class="d-flex justify-content-end gap-2 mb-3">
    <button type="button" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Simulaciones guardadas</button>
    <button type="button" class="btn btn-sm-accent" disabled><i class="bi bi-save"></i> Guardar simulación</button>
</div>

<div class="row g-3 mb-3">
    {{-- Procesos de entrada --}}
    <div class="col-xl-8"><div class="sm-card h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Procesos</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Agregar</button>
                <button type="button" class="btn btn-sm btn-outline-secondary"><i class="bi bi-shuffle"></i> Generar aleatorio</button>
            </div>
        </div>
        <table class="table align-middle mb-2">
            <thead>
                <tr class="text-secondary" style="font-size: 12px; text-transform: uppercase">
                    <th style="width: 90px">Proceso</th><th>Llegada</th><th>Ráfaga CPU</th><th>Prioridad</th><th style="width: 50px"></th>
                </tr>
            </thead>
            <tbody id="filas-procesos">
                @for ($i = 1; $i <= 3; $i++)
                    <tr>
                        <td class="sm-num fw-semibold">P{{ $i }}</td>
                        <td><input type="number" min="0" class="form-control sm-mono" aria-label="Llegada P{{ $i }}"></td>
                        <td><input type="number" min="1" class="form-control sm-mono" aria-label="Ráfaga P{{ $i }}"></td>
                        <td><input type="number" min="0" class="form-control sm-mono" aria-label="Prioridad P{{ $i }}"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Quitar P{{ $i }}"><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        <small class="text-secondary">Prioridad: número menor = mayor prioridad.</small>
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
            <input id="quantum" type="number" min="1" value="2" class="form-control sm-mono" style="width: 90px">
            <small class="text-secondary">solo Round Robin</small>
        </div>
        <button type="button" class="btn btn-sm-dark btn-lg mt-auto">Simular</button>
    </div></div>
</div>

{{-- Diagrama de Gantt --}}
<div class="sm-card mb-3">
    <h2>Diagrama de Gantt</h2>
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
                        <tr><td>{{ $nombre }}</td><td class="text-end sm-num">—</td><td class="text-end sm-num">—</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
