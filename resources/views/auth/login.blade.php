<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SysMonitor Web</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/sysmonitor.css') }}" rel="stylesheet">
    <style>
        body { display: grid; place-items: center; min-height: 100vh; background: var(--sm-side); }
        .login { width: min(400px, 92vw); }
        .login .sm-card { padding: 32px; }
    </style>
</head>
<body>
<div class="login">
    <div class="text-center mb-4" style="color:#fff;font-size:22px;font-weight:600">
        <span style="color:#4fd1a5">▲</span> SysMonitor <span class="sm-brand-sub">web</span>
    </div>
    <div class="sm-card">
        <h2 class="mb-1" style="font-size:20px">Iniciar sesión</h2>
        <p class="text-secondary mb-4" style="font-size:14px">Monitoreo y administración del servidor Linux.</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control" id="password" name="password" type="password" required>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" id="recordar" name="recordar" type="checkbox">
                <label class="form-check-label" for="recordar">Mantener sesión</label>
            </div>
            <button class="btn w-100 text-white" style="background:var(--sm-accent)">Entrar</button>
        </form>
    </div>
    <p class="text-center mt-3" style="color:#8a9893;font-size:12px">Sin registro público · cuentas asignadas por el administrador</p>
</div>
</body>
</html>
