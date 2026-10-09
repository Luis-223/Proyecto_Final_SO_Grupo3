@php
    $menu = [
        ['dashboard', 'bi-grid-1x2', 'Dashboard', null],
        ['procesos.index', 'bi-cpu', 'Procesos', 'M1'],
        ['cpu-memoria.index', 'bi-activity', 'CPU y Memoria', 'M2'],
        ['planificacion.index', 'bi-bar-chart-steps', 'Planificación', 'M3'],
        ['interbloqueos.index', 'bi-diagram-3', 'Interbloqueos', 'M4'],
        ['almacenamiento.index', 'bi-device-hdd', 'Almacenamiento', 'M5'],
    ];
@endphp
<aside class="sm-sidebar">
    <a href="{{ route('dashboard') }}" class="sm-brand">
        <span class="sm-brand-mark">▲</span> SysMonitor<span class="sm-brand-sub">web</span>
    </a>

    <nav class="sm-nav">
        @foreach ($menu as [$ruta, $icono, $texto, $codigo])
            <a href="{{ route($ruta) }}" class="sm-nav-link {{ request()->routeIs(str_replace('.index', '*', $ruta)) ? 'active' : '' }}">
                <i class="bi {{ $icono }}"></i>
                <span>{{ $texto }}</span>
                @if ($codigo)<small>{{ $codigo }}</small>@endif
            </a>
        @endforeach

        @if (auth()->user()->esAdministrador())
            <div class="sm-nav-sep">Administración</div>
            <a href="{{ route('bitacora.index') }}" class="sm-nav-link {{ request()->routeIs('bitacora.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Bitácora</span><small>M6</small>
            </a>
        @endif
    </nav>

    <div class="sm-sidebar-foot">
        <div class="sm-user">{{ auth()->user()->name }}</div>
        <div class="sm-user-mail">{{ auth()->user()->email }}</div>
    </div>
</aside>
