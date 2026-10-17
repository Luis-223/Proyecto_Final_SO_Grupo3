@extends('layouts.app')

@section('titulo', 'Almacenamiento')
@section('modulo', 'M5 · lsblk · df -h · findmnt · /etc/passwd · who')

@section('contenido')
@php
    $colorUso = fn (int $pct) => $pct > 90 ? '#b93a26' : ($pct >= 70 ? '#c7801a' : '#1a7a5c');
    $error = fn (string $seccion) => isset($errores[$seccion])
        ? '<tr><td colspan="6" class="sm-vacio text-danger">No se pudo leer: '.e($errores[$seccion]).'</td></tr>'
        : null;
@endphp

<div class="row g-3 mb-3">
    {{-- Discos y particiones --}}
    <div class="col-xl-6">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Discos y particiones</h2>
                <span class="badge text-bg-light border sm-mono" id="tabla-particiones">
                    Tabla: {{ $bloques['tablas'] ? collect($bloques['tablas'])->map(fn ($t, $d) => "$d $t")->implode(' · ') : '—' }}
                </span>
            </div>
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Tamaño</th><th>Tipo</th><th>Sistema de archivos</th><th>Montaje</th></tr></thead>
                <tbody id="bloques">
                    {!! $error('bloques') !!}
                    @forelse ($bloques['filas'] as $b)
                        <tr>
                            <td class="sm-mono" style="padding-left: {{ 12 + $b['nivel'] * 18 }}px">{{ $b['nivel'] ? '└ ' : '' }}{{ $b['nombre'] }}</td>
                            <td class="sm-num">{{ $b['tamano'] }}</td>
                            <td>{{ $b['tipo'] }}</td>
                            <td class="sm-mono">{{ $b['fs'] ?? '—' }}</td>
                            <td class="sm-mono">{{ $b['montaje'] ?? '—' }}</td>
                        </tr>
                    @empty
                        @unless (isset($errores['bloques']))
                            <tr><td colspan="5" class="sm-vacio">No hay discos para mostrar.</td></tr>
                        @endunless
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 pb-3 sm-fuente">Tabla de particiones: MBR (dos) o GPT, columna PTTYPE de lsblk.</div>
        </div>
    </div>

    {{-- Uso de sistemas de archivos --}}
    <div class="col-xl-6"><div class="sm-card h-100 d-flex flex-column">
        <h2>Sistemas de archivos montados</h2>
        <div id="uso-fs" class="flex-grow-1 d-flex flex-column gap-3">
            @if (isset($errores['sistemas']))
                <div class="sm-area-vacia text-danger" style="min-height: 120px">No se pudo ejecutar df: {{ $errores['sistemas'] }}</div>
            @endif
            @forelse ($sistemas as $fs)
                <div>
                    <div class="d-flex justify-content-between" style="font-size: 14px">
                        <span><strong class="sm-mono">{{ $fs['montaje'] }}</strong> <span class="text-secondary sm-mono" style="font-size: 12px">{{ $fs['origen'] }} · {{ $fs['tipo'] }}</span></span>
                        <span class="sm-num">{{ $fs['usado'] }} / {{ $fs['tamano'] }} · <strong>{{ $fs['pct'] }} %</strong></span>
                    </div>
                    <div class="progress mt-1" style="height: 12px" role="progressbar" aria-valuenow="{{ $fs['pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Uso de {{ $fs['montaje'] }}">
                        <div class="progress-bar" style="width: {{ $fs['pct'] }}%; background: {{ $colorUso($fs['pct']) }}"></div>
                    </div>
                    <div class="sm-fuente mt-1">Libre: {{ $fs['libre'] }}</div>
                </div>
            @empty
                @unless (isset($errores['sistemas']))
                    <div class="sm-area-vacia" style="min-height: 120px">No hay sistemas de archivos para mostrar.</div>
                @endunless
            @endforelse
        </div>
        <div class="d-flex gap-3 mt-3" style="font-size: 12px; color: #3d4643">
            <span><span class="sm-leyenda" style="background: #1a7a5c"></span>&lt; 70 %</span>
            <span><span class="sm-leyenda" style="background: #c7801a"></span>70–90 %</span>
            <span><span class="sm-leyenda" style="background: #b93a26"></span>&gt; 90 %</span>
        </div>
    </div></div>
</div>

{{-- fstab / findmnt --}}
<div class="sm-card sm-card-tabla mb-3">
    <div class="sm-card-head d-flex justify-content-between align-items-baseline">
        <h2 class="mb-0">Puntos de montaje persistentes</h2>
        <span class="sm-fuente">/etc/fstab · findmnt --fstab</span>
    </div>
    <table class="table align-middle">
        <thead><tr><th>Dispositivo</th><th>Montaje</th><th>Tipo</th><th>Opciones</th><th>dump / pass</th></tr></thead>
        <tbody id="fstab">
            {!! $error('fstab') !!}
            @forelse ($fstab as $f)
                <tr>
                    <td class="sm-mono text-break">{{ $f['dispositivo'] }}</td>
                    <td class="sm-mono">{{ $f['montaje'] }}</td>
                    <td class="sm-mono">{{ $f['tipo'] }}</td>
                    <td class="sm-mono text-break" style="font-size: 12px">{{ $f['opciones'] }}</td>
                    <td class="sm-num">{{ $f['dump_pass'] }}</td>
                </tr>
            @empty
                @unless (isset($errores['fstab']))
                    <tr><td colspan="5" class="sm-vacio">No hay puntos de montaje en /etc/fstab.</td></tr>
                @endunless
            @endforelse
        </tbody>
    </table>
</div>

<div class="row g-3">
    {{-- Usuarios y sesiones --}}
    <div class="col-xl-5">
        <div class="sm-card sm-card-tabla mb-3">
            <div class="sm-card-head d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Usuarios del sistema</h2>
                <ul class="nav nav-pills nav-sm" role="tablist" style="font-size: 13px">
                    <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="tab" data-bs-target="#usuarios-humanos" type="button">Humanos ({{ count($usuarios['humanos']) }})</button></li>
                    <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#usuarios-sistema" type="button">Sistema ({{ count($usuarios['sistema']) }})</button></li>
                </ul>
            </div>
            <div class="tab-content" style="max-height: 360px; overflow: auto">
                @foreach (['usuarios-humanos' => 'humanos', 'usuarios-sistema' => 'sistema'] as $id => $grupo)
                    <div class="tab-pane fade {{ $grupo === 'humanos' ? 'show active' : '' }}" id="{{ $id }}">
                        <table class="table align-middle">
                            <thead><tr><th>Usuario</th><th>UID</th><th>Home</th><th>Shell</th></tr></thead>
                            <tbody>
                                @forelse ($usuarios[$grupo] as $u)
                                    <tr>
                                        <td class="fw-semibold">{{ $u['usuario'] }}</td>
                                        <td class="sm-num">{{ $u['uid'] }}</td>
                                        <td class="sm-mono">{{ $u['home'] }}</td>
                                        <td class="sm-mono">{{ $u['shell'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="sm-vacio">No hay usuarios para mostrar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
            <div class="px-3 pb-3 pt-2" style="font-size: 12px; color: #5f6965">Humano: UID ≥ 1000 y shell de login. Sistema: UID &lt; 1000 o shell nologin.</div>
        </div>

        <div class="sm-card sm-card-tabla">
            <div class="sm-card-head d-flex justify-content-between align-items-baseline">
                <h2 class="mb-0">Sesiones activas</h2>
                <span class="sm-fuente">who</span>
            </div>
            <table class="table align-middle">
                <thead><tr><th>Usuario</th><th>Terminal</th><th>Desde</th><th>Origen</th></tr></thead>
                <tbody id="sesiones">
                    {!! $error('sesiones') !!}
                    @forelse ($sesiones as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s['usuario'] }}</td>
                            <td class="sm-mono">{{ $s['terminal'] }}</td>
                            <td class="sm-num">{{ $s['desde'] }}</td>
                            <td class="sm-mono">{{ $s['origen'] }}</td>
                        </tr>
                    @empty
                        @unless (isset($errores['sesiones']))
                            <tr><td colspan="4" class="sm-vacio">No hay sesiones para mostrar.</td></tr>
                        @endunless
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Explorador de solo lectura --}}
    <div class="col-xl-7" id="explorador-card">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="mb-0">Explorador (solo lectura)</h2>
                <nav aria-label="Ruta" class="sm-mono" id="ruta-actual">
                    @php
                        $relativa = trim(substr($explorador['ruta'], strlen(realpath($raiz) ?: $raiz)), '/');
                        $acumulada = '';
                    @endphp
                    <a href="{{ route('almacenamiento.index') }}#explorador-card">{{ $raiz }}</a>
                    @foreach (array_filter(explode('/', $relativa), 'strlen') as $parte)
                        @php $acumulada = ltrim($acumulada.'/'.$parte, '/'); @endphp
                        / <a href="{{ route('almacenamiento.index', ['ruta' => $acumulada]) }}#explorador-card">{{ $parte }}</a>
                    @endforeach
                </nav>
            </div>
            <div style="max-height: 560px; overflow: auto">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Permisos</th><th>Propietario</th><th>Grupo</th><th class="text-end">Tamaño</th><th>Nombre</th></tr></thead>
                    <tbody id="explorador">
                        {!! $error('explorador') !!}
                        @if ($explorador['padre'] !== null)
                            <tr>
                                <td colspan="4"></td>
                                <td><a href="{{ route('almacenamiento.index', array_filter(['ruta' => $explorador['padre']], 'strlen')) }}#explorador-card" class="sm-mono"><i class="bi bi-arrow-90deg-up"></i> ..</a></td>
                            </tr>
                        @endif
                        @forelse ($explorador['entradas'] as $en)
                            <tr>
                                <td class="sm-mono">{{ $en['permisos'] }}</td>
                                <td>{{ $en['propietario'] }}</td>
                                <td>{{ $en['grupo'] }}</td>
                                <td class="text-end sm-num">{{ \App\Services\Sistema\Almacenamiento::legible($en['tamano']) }}</td>
                                <td class="sm-mono">
                                    @if ($en['dir'])
                                        <a href="{{ route('almacenamiento.index', ['ruta' => $en['relativa']]) }}#explorador-card"><i class="bi bi-folder2"></i> {{ $en['nombre'] }}/</a>
                                    @elseif ($en['enlace'])
                                        <i class="bi bi-link-45deg"></i> {{ $en['nombre'] }}
                                    @else
                                        <i class="bi bi-file-earmark text-secondary"></i> {{ $en['nombre'] }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="sm-vacio">Carpeta vacía o sin permiso de lectura.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-3 pb-3 pt-2" style="font-size: 12px; color: #5f6965">Solo se permite navegar dentro de {{ $raiz }}. Las rutas se resuelven con realpath(): ni «..» ni enlaces simbólicos permiten salir. No se abre ni se descarga contenido.</div>
        </div>
    </div>
</div>
@endsection
