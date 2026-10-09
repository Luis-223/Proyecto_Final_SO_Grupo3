<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Dashboard') · SysMonitor Web</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/sysmonitor.css') }}" rel="stylesheet">
    @stack('estilos')
</head>
<body>
<div class="sm-shell">
    @include('partials.sidebar')

    <main class="sm-main">
        <header class="sm-topbar">
            <div>
                <div class="sm-eyebrow">@yield('modulo', 'Inicio')</div>
                <h1 class="sm-title">@yield('titulo', 'Dashboard')</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="sm-host"><i class="bi bi-hdd-network"></i> {{ gethostname() }}</span>
                <span class="badge sm-rol sm-rol-{{ auth()->user()->rol }}">{{ ucfirst(auth()->user()->rol) }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Salir</button>
                </form>
            </div>
        </header>

        @if (session('ok'))
            <div class="alert alert-success">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @yield('contenido')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@stack('scripts')
</body>
</html>
