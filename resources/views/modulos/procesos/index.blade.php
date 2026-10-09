@extends('layouts.app')

@section('titulo', 'Procesos')
@section('modulo', 'M1 · /proc/[pid]/stat · /proc/[pid]/status')

@section('contenido')
@php
    $estados = [['R', 'Ejecución'], ['S', 'Durmiendo'], ['D', 'Espera E/S'], ['Z', 'Zombi'], ['T', 'Detenido']];
    $esAdmin = auth()->user()->esAdministrador();
@endphp

{{-- Resumen por estado --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    @foreach ($estados as [$letra, $nombre])
        <div class="sm-chip">
            <span class="sm-estado sm-estado-{{ $letra }}">{{ $letra }}</span>
            <span class="text-secondary">{{ $nombre }}</span>
            <strong class="sm-num" id="total-{{ $letra }}">—</strong>
        </div>
    @endforeach
    <span class="ms-auto sm-fuente" id="total-procesos">— procesos</span>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vista-tabla" type="button" role="tab">Tabla</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vista-arbol" type="button" role="tab">Árbol</button>
    </li>
</ul>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="tab-content">
            {{-- Vista de tabla --}}
            <div class="tab-pane fade show active" id="vista-tabla" role="tabpanel">
                <div class="sm-card sm-card-tabla">
                    <div class="sm-card-head d-flex flex-wrap gap-2">
                        <label for="buscar" class="visually-hidden">Buscar</label>
                        <input id="buscar" type="search" class="form-control" style="max-width: 360px" placeholder="Buscar por PID, usuario o comando…">
                        <label for="filtro-estado" class="visually-hidden">Estado</label>
                        <select id="filtro-estado" class="form-select" style="max-width: 200px">
                            <option value="">Todos los estados</option>
                            @foreach ($estados as [$letra, $nombre])
                                <option value="{{ $letra }}">{{ $letra }} · {{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if ($esAdmin)
                            <button type="button" class="btn btn-sm-accent ms-auto">
                                <i class="bi bi-plus-lg"></i> Lanzar proceso de prueba
                            </button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="tabla-procesos">
                            <thead>
                                <tr>
                                    <th>PID</th><th>PPID</th><th>Usuario</th><th>Estado</th>
                                    <th>Nice</th><th>% CPU</th><th>Memoria</th><th>Comando</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="8" class="sm-vacio">No hay procesos para mostrar.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Vista de árbol --}}
            <div class="tab-pane fade" id="vista-arbol" role="tabpanel">
                <div class="sm-card sm-card-tabla">
                    <div class="sm-card-head d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Expandir todo</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">Contraer todo</button>
                        <div class="form-check ms-2 mb-0">
                            <input class="form-check-input" type="checkbox" id="ocultar-kernel" checked>
                            <label class="form-check-label" for="ocultar-kernel">Ocultar hilos del kernel</label>
                        </div>
                        <span class="ms-auto sm-fuente">raíz: PID 1</span>
                    </div>
                    <div class="p-3" id="arbol-procesos">
                        <div class="sm-area-vacia">No hay procesos para mostrar.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Panel de acciones --}}
    <div class="col-xl-3">
        <div class="sm-card d-flex flex-column gap-3">
            <div>
                <div class="sm-kpi-label">Proceso seleccionado</div>
                <div class="sm-num fs-5" id="proceso-seleccionado">—</div>
                <small class="text-secondary">Solo procesos de prueba lanzados por la aplicación.</small>
            </div>

            @if ($esAdmin)
                <div>
                    <div class="fw-semibold mb-2" style="font-size: 14px">Enviar señal</div>
                    <div class="row g-2">
                        @foreach (['SIGTERM', 'SIGKILL', 'SIGSTOP', 'SIGCONT'] as $senal)
                            <div class="col-6">
                                <button type="button" class="btn w-100 sm-mono {{ $senal === 'SIGKILL' ? 'btn-outline-danger' : 'btn-outline-secondary' }}" disabled>{{ $senal }}</button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label for="nice" class="fw-semibold mb-2" style="font-size: 14px">Prioridad (renice)</label>
                    <input id="nice" type="range" class="form-range" min="0" max="19" value="0" disabled>
                    <div class="d-flex justify-content-between sm-fuente"><span>0</span><span>19</span></div>
                    <button type="button" class="btn btn-sm-accent w-100 mt-2" disabled>Aplicar renice</button>
                </div>
                <div class="p-2 rounded" style="background: #f7f8f6; font-size: 12px; color: #3d4643">
                    Cada acción queda registrada en la bitácora. www-data solo puede subir el nice (bajar prioridad).
                </div>
            @else
                <div class="p-2 rounded" style="background: #f7f8f6; font-size: 13px; color: #3d4643">
                    Su rol es <strong>Observador</strong>: puede consultar los procesos, pero no enviar señales ni cambiar prioridades.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
