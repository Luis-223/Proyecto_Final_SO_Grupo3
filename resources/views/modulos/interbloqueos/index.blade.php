@extends('layouts.app')

@section('titulo', 'Interbloqueos')
@section('modulo', 'M4 · algoritmo del banquero · grafo de asignación')

@section('contenido')
@php
    $n = 5; // procesos
    $m = 3; // tipos de recurso
    $recursos = array_slice(['A', 'B', 'C', 'D', 'E', 'F'], 0, $m);
    $columnas = "grid-template-columns: 40px repeat({$m}, minmax(0, 1fr));";
@endphp

{{-- Dimensiones --}}
<div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3" style="font-size: 14px">
    <label for="n">Procesos (n)</label>
    <input id="n" type="number" min="1" max="10" value="{{ $n }}" class="form-control sm-mono" style="width: 80px">
    <label for="m" class="ms-2">Recursos (m)</label>
    <input id="m" type="number" min="1" max="6" value="{{ $m }}" class="form-control sm-mono" style="width: 80px">
    <button type="button" class="btn btn-outline-secondary ms-2">Cargar ejemplo</button>
    <button type="button" class="btn btn-sm-dark">Evaluar estado</button>
</div>

{{-- Matrices --}}
<div class="sm-card mb-3">
    <div class="row g-4">
        @foreach (['asignacion' => 'Asignación', 'maximo' => 'Máximo', 'necesidad' => 'Necesidad'] as $clave => $titulo)
            <div class="col-md-6 col-xl-3">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <h2 class="mb-0">{{ $titulo }}</h2>
                    <small class="text-secondary">{{ $clave === 'necesidad' ? 'Máximo − Asignación' : 'entrada' }}</small>
                </div>
                <div class="sm-matriz" style="{{ $columnas }}">
                    <span></span>
                    @foreach ($recursos as $r)<span class="text-center sm-fuente">{{ $r }}</span>@endforeach
                    @for ($i = 0; $i < $n; $i++)
                        <span class="sm-num fw-semibold">P{{ $i }}</span>
                        @foreach ($recursos as $r)
                            @if ($clave === 'necesidad')
                                <span class="sm-celda" id="necesidad-{{ $i }}-{{ $r }}">—</span>
                            @else
                                <input type="number" min="0" class="form-control" name="{{ $clave }}[{{ $i }}][{{ $r }}]" aria-label="{{ $titulo }} P{{ $i }} {{ $r }}">
                            @endif
                        @endforeach
                    @endfor
                </div>
            </div>
        @endforeach

        <div class="col-md-6 col-xl-3 d-flex flex-column gap-3">
            <div>
                <h2 class="mb-2">Disponible</h2>
                <div class="sm-matriz" style="grid-template-columns: repeat({{ $m }}, minmax(0, 1fr));">
                    @foreach ($recursos as $r)<span class="text-center sm-fuente">{{ $r }}</span>@endforeach
                    @foreach ($recursos as $r)
                        <input type="number" min="0" class="form-control" name="disponible[{{ $r }}]" aria-label="Disponible {{ $r }}">
                    @endforeach
                </div>
            </div>
            <div id="estado-sistema" class="p-3 rounded" style="background: #f7f8f6; font-size: 14px; color: #3d4643">
                <div class="fw-semibold">Estado del sistema</div>
                <div class="sm-mono">—</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Paso a paso --}}
    <div class="col-xl-7">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head"><h2 class="mb-0">Paso a paso del algoritmo de seguridad</h2></div>
            <table class="table align-middle">
                <thead><tr><th>Paso</th><th>Proceso</th><th>¿Necesidad ≤ Trabajo?</th><th>Trabajo nuevo</th><th>Resultado</th></tr></thead>
                <tbody id="pasos">
                    <tr><td colspan="5" class="sm-vacio">Ingrese las matrices y presione “Evaluar estado”.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Solicitud de recursos --}}
    <div class="col-xl-5"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Evaluar solicitud de recursos</h2>
        <div class="d-flex flex-wrap align-items-center gap-2" style="font-size: 14px">
            <label for="proceso-solicitud">Proceso</label>
            <select id="proceso-solicitud" class="form-select sm-mono" style="width: auto">
                @for ($i = 0; $i < $n; $i++)<option>P{{ $i }}</option>@endfor
            </select>
            <span class="ms-1">solicita</span>
            @foreach ($recursos as $r)
                <input type="number" min="0" class="form-control sm-mono" style="width: 58px" placeholder="{{ $r }}" aria-label="Solicitud {{ $r }}">
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-secondary">Evaluar solicitud</button>
        <div id="resultado-solicitud" class="sm-area-vacia mt-auto" style="min-height: 90px">El resultado de la solicitud aparecerá aquí.</div>
    </div></div>
</div>

<div class="row g-3">
    {{-- Grafo --}}
    <div class="col-xl-7"><div class="sm-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
            <h2 class="mb-0">Grafo de asignación de recursos</h2>
            <small class="text-secondary">○ proceso · □ recurso · P → R solicita · R → P asignado</small>
        </div>
        <div id="grafo" class="sm-area-vacia" style="min-height: 240px">El grafo se dibuja al evaluar el estado.</div>
    </div></div>

    {{-- Detección y recuperación --}}
    <div class="col-xl-5"><div class="sm-card h-100 d-flex flex-column gap-3">
        <h2 class="mb-0">Detección y recuperación</h2>
        <div class="p-3 rounded" style="background: #f7f8f6; font-size: 14px">
            <div class="fw-semibold">Ciclos detectados</div>
            <div class="sm-mono" id="ciclos">—</div>
        </div>
        <div>
            <div class="fw-semibold mb-2" style="font-size: 14px">Estrategias de recuperación</div>
            <div class="border rounded p-2 mb-2" style="font-size: 13px"><strong>Terminación:</strong> <span id="sugerencia-terminacion">—</span></div>
            <div class="border rounded p-2" style="font-size: 13px"><strong>Apropiación:</strong> <span id="sugerencia-apropiacion">—</span></div>
        </div>
    </div></div>
</div>
@endsection
