@extends('layouts.app')

@section('titulo', 'Usuarios')
@section('modulo', 'Administración · M6')

@section('contenido')
<div class="row g-3">
    {{-- Crear cuenta --}}
    <div class="col-xl-4">
        <div class="sm-card">
            <h2>Crear cuenta de Observador</h2>
            <form method="POST" action="{{ route('usuarios.store') }}" autocomplete="off" class="d-flex flex-column gap-3">
                @csrf
                <div>
                    <label for="name" class="form-label">Nombre</label>
                    <input id="name" name="name" type="text" class="form-control" maxlength="100" required value="{{ old('name') }}">
                </div>
                <div>
                    <label for="email" class="form-label">Correo</label>
                    <input id="email" name="email" type="email" class="form-control" maxlength="150" required value="{{ old('email') }}">
                    <div class="form-text">Se guarda en minúsculas; con ese correo inicia sesión.</div>
                </div>
                <div>
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="8" required autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres.</div>
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Repetir contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-sm-accent"><i class="bi bi-person-plus"></i> Crear cuenta</button>
            </form>
            <div class="p-2 rounded mt-3" style="background: #f7f8f6; font-size: 12px; color: #3d4643">
                Las cuentas nuevas siempre tienen rol <strong>Observador</strong> (solo lectura).
                No hay registro público: solo un Administrador puede crear cuentas, y cada una queda en la bitácora.
            </div>
        </div>
    </div>

    {{-- Lista de cuentas --}}
    <div class="col-xl-8">
        <div class="sm-card sm-card-tabla">
            <div class="sm-card-head d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Cuentas de la aplicación</h2>
                <span class="sm-fuente">{{ count($usuarios) }} cuentas</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creada</th></tr></thead>
                    <tbody>
                        @forelse ($usuarios as $u)
                            <tr>
                                <td class="fw-semibold">{{ $u->name }}</td>
                                <td class="sm-mono">{{ $u->email }}</td>
                                <td><span class="badge sm-rol sm-rol-{{ $u->rol }}">{{ ucfirst($u->rol) }}</span></td>
                                <td class="sm-num">{{ $u->created_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="sm-vacio">No hay cuentas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
