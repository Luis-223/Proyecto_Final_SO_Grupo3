@extends('layouts.app')

@section('titulo', 'Procesos')
@section('modulo', 'M1 · /proc/[pid]/stat · /proc/[pid]/status')

@section('contenido')
@php
    $estados = [['R', 'Ejecución'], ['S', 'Durmiendo'], ['D', 'Espera E/S'], ['Z', 'Zombi'], ['T', 'Detenido']];
    $esAdmin = auth()->user()->esAdministrador();
@endphp

<div id="m1"
     data-url-datos="{{ route('api.procesos') }}"
     data-url-lanzar="{{ $esAdmin ? route('procesos.lanzar') : '' }}"
     data-url-base="{{ url('procesos') }}"
     data-admin="{{ $esAdmin ? '1' : '0' }}">

{{-- Resumen por estado --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    @foreach ($estados as [$letra, $nombre])
        <button type="button" class="sm-chip border-0" data-filtro-estado="{{ $letra }}" title="Filtrar por {{ $nombre }}">
            <span class="sm-estado sm-estado-{{ $letra }}">{{ $letra }}</span>
            <span class="text-secondary">{{ $nombre }}</span>
            <strong class="sm-num" id="total-{{ $letra }}">—</strong>
        </button>
    @endforeach
    <span class="sm-chip" title="Hilos del kernel inactivos (I) y otros estados">
        <span class="sm-estado" style="background: #a3aca8">I</span>
        <span class="text-secondary">Inactivo kernel</span>
        <strong class="sm-num" id="total-I">—</strong>
    </span>
    <span class="ms-auto sm-fuente" id="total-procesos">— procesos</span>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <ul class="nav nav-tabs flex-grow-1" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vista-tabla" type="button" role="tab">Tabla</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vista-arbol" type="button" role="tab">Árbol</button>
        </li>
    </ul>
    <span class="sm-fuente" id="ultima-lectura"></span>
    <div class="form-check form-switch mb-0 ms-2" style="font-size: 14px">
        <input class="form-check-input" type="checkbox" id="auto" checked>
        <label class="form-check-label" for="auto">Actualizar cada 5 s</label>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="refrescar"><i class="bi bi-arrow-clockwise"></i></button>
</div>

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
                            <option value="I">I · Inactivo (kernel)</option>
                            <option value="prueba">Solo procesos de prueba</option>
                        </select>
                        @if ($esAdmin)
                            <div class="dropdown ms-auto">
                                <button type="button" class="btn btn-sm-accent dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-plus-lg"></i> Lanzar proceso de prueba
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @foreach ($tipos as $clave => $tipo)
                                        <li><button type="button" class="dropdown-item" data-lanzar="{{ $clave }}">{{ $tipo['etiqueta'] }}</button></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="table-responsive" style="max-height: 620px">
                        <table class="table table-hover align-middle sm-tabla-ordenable" id="tabla-procesos">
                            <thead class="sticky-top">
                                <tr>
                                    <th data-orden="pid">PID</th><th data-orden="ppid">PPID</th><th data-orden="usuario">Usuario</th>
                                    <th data-orden="estado">Estado</th><th data-orden="nice">Nice</th><th data-orden="cpu">% CPU</th>
                                    <th data-orden="mem_kb">Memoria</th><th data-orden="comando">Comando</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="8" class="sm-vacio">Leyendo /proc…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Vista de árbol --}}
            <div class="tab-pane fade" id="vista-arbol" role="tabpanel">
                <div class="sm-card sm-card-tabla">
                    <div class="sm-card-head d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="expandir">Expandir todo</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="contraer">Contraer todo</button>
                        <div class="form-check ms-2 mb-0">
                            <input class="form-check-input" type="checkbox" id="ocultar-kernel" checked>
                            <label class="form-check-label" for="ocultar-kernel">Ocultar hilos del kernel</label>
                        </div>
                        <span class="ms-auto sm-fuente">raíz: PID 1 · relación PPID → PID</span>
                    </div>
                    <div class="p-3 sm-arbol" id="arbol-procesos" style="max-height: 620px; overflow: auto">
                        <div class="sm-area-vacia">Leyendo /proc…</div>
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
                <small class="text-secondary" id="detalle-seleccionado">Haga clic en una fila. Solo los procesos de prueba lanzados por la aplicación aceptan acciones.</small>
            </div>

            @if ($esAdmin)
                <div>
                    <div class="fw-semibold mb-2" style="font-size: 14px">Enviar señal</div>
                    <div class="row g-2">
                        @foreach (['TERM' => 'SIGTERM', 'KILL' => 'SIGKILL', 'STOP' => 'SIGSTOP', 'CONT' => 'SIGCONT'] as $valor => $senal)
                            <div class="col-6">
                                <button type="button" data-senal="{{ $valor }}" class="btn w-100 sm-mono {{ $senal === 'SIGKILL' ? 'btn-outline-danger' : 'btn-outline-secondary' }}" disabled>{{ $senal }}</button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label for="nice" class="fw-semibold mb-2" style="font-size: 14px">Prioridad (renice): <span class="sm-num" id="nice-valor">0</span></label>
                    <input id="nice" type="range" class="form-range" min="0" max="19" value="0" disabled>
                    <div class="d-flex justify-content-between sm-fuente"><span>0</span><span>19</span></div>
                    <button type="button" class="btn btn-sm-accent w-100 mt-2" id="aplicar-renice" disabled>Aplicar renice</button>
                </div>
                <div class="p-2 rounded" style="background: #f7f8f6; font-size: 12px; color: #3d4643">
                    Cada acción queda registrada en la bitácora. www-data solo puede subir el nice (bajar prioridad).
                </div>
            @else
                <div class="p-2 rounded" style="background: #f7f8f6; font-size: 13px; color: #3d4643">
                    Su rol es <strong>Observador</strong>: puede consultar los procesos, pero no enviar señales ni cambiar prioridades.
                </div>
            @endif

            <div>
                <div class="fw-semibold mb-2" style="font-size: 14px">Procesos de prueba activos</div>
                <div id="lista-pruebas" class="d-flex flex-column gap-1" style="font-size: 13px">
                    <span class="text-secondary">Ninguno.</span>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('estilos')
<style>
    #m1 .sm-chip { cursor: pointer; }
    #m1 .sm-card-tabla { overflow: visible; }            /* que el menú "Lanzar" no quede recortado */
    #m1 .sm-card-head .dropdown-menu { z-index: 1030; }
    #tabla-procesos thead.sticky-top { z-index: 1; }
    .sm-tabla-ordenable th[data-orden] { cursor: pointer; user-select: none; }
    .sm-tabla-ordenable th[data-orden].orden-asc::after { content: ' ▲'; font-size: 10px; }
    .sm-tabla-ordenable th[data-orden].orden-desc::after { content: ' ▼'; font-size: 10px; }
    #tabla-procesos tbody tr { cursor: pointer; }
    #tabla-procesos tbody tr.seleccionado td { background: #e2f3ec; }
    #tabla-procesos td.sm-comando { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sm-badge-prueba { font-size: 10px; background: #1a7a5c; color: #fff; border-radius: 4px; padding: 1px 5px; margin-left: 4px; }
    .sm-arbol ul { list-style: none; padding-left: 20px; margin: 0; border-left: 1px dashed #cfd5cf; }
    .sm-arbol > ul { border-left: 0; padding-left: 0; }
    .sm-arbol li { padding: 2px 0; }
    .sm-arbol .nodo { font-family: var(--sm-mono); font-size: 13px; cursor: pointer; border-radius: 4px; padding: 1px 4px; }
    .sm-arbol .nodo:hover { background: #eef1ee; }
    .sm-arbol .plegar { width: 18px; display: inline-block; color: #5f6965; cursor: pointer; }
    .sm-arbol li.cerrado > ul { display: none; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/m1-procesos.js') }}?v=1"></script>
@endpush
