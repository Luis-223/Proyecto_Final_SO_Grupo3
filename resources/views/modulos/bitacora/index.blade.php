@extends('layouts.app')

@section('titulo', 'Bitácora')
@section('modulo', 'Administración · M6')

@section('contenido')
<div class="sm-card p-0 overflow-auto">
    <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>PID</th><th>Detalle</th><th>Resultado</th><th>IP</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($registros as $r)
            <tr>
                <td class="sm-mono">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td>
                <td>{{ $r->usuario?->name ?? '—' }}</td>
                <td class="sm-mono">{{ $r->accion }}</td>
                <td class="sm-mono">{{ $r->pid ?? '—' }}</td>
                <td>{{ $r->detalle }}</td>
                <td>
                    <span class="badge {{ match ($r->resultado) { 'exito' => 'text-bg-success', 'error' => 'text-bg-danger', default => 'text-bg-warning' } }}">{{ $r->resultado }}</span>
                </td>
                <td class="sm-mono text-secondary">{{ $r->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-secondary py-4">Sin registros todavía.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $registros->links() }}</div>
@endsection
