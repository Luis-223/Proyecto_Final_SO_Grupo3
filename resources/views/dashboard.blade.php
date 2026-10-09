@extends('layouts.app')

@section('titulo', 'Dashboard')
@section('modulo', 'Inicio · M6')

@section('contenido')
@php
    $d = intdiv((int) $uptime, 86400);
    $h = intdiv((int) $uptime % 86400, 3600);
    $m = intdiv((int) $uptime % 3600, 60);
@endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="sm-card">
        <div class="sm-kpi-label">Carga promedio (1 / 5 / 15 min)</div>
        <div class="sm-kpi-value sm-mono" style="font-size:22px">{{ implode(' · ', $carga) }}</div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="sm-card">
        <div class="sm-kpi-label">Memoria en uso</div>
        <div class="sm-kpi-value">{{ $memUsadaPct }}%</div>
        <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ $memUsadaPct }}%;background:var(--sm-accent)"></div></div>
        <small class="text-secondary">de {{ number_format($memTotalMb) }} MB</small>
    </div></div>
    <div class="col-6 col-lg-3"><div class="sm-card">
        <div class="sm-kpi-label">Procesos</div>
        <div class="sm-kpi-value">{{ $procesos }}</div>
        <small class="text-secondary">directorios numéricos en /proc</small>
    </div></div>
    <div class="col-6 col-lg-3"><div class="sm-card">
        <div class="sm-kpi-label">Tiempo encendido</div>
        <div class="sm-kpi-value">{{ $d }}d {{ $h }}h {{ $m }}m</div>
        <small class="text-secondary">/proc/uptime</small>
    </div></div>
</div>

<div class="sm-card">
    <h2>Últimas acciones administrativas</h2>
    @forelse ($ultimas as $r)
        <div class="d-flex gap-3 border-top py-2 sm-mono">
            <span class="text-secondary">{{ $r->created_at?->format('d/m H:i:s') }}</span>
            <span>{{ $r->usuario?->name ?? '—' }}</span>
            <span>{{ $r->accion }}</span>
            <span>{{ $r->pid }}</span>
            <span class="ms-auto">{{ $r->resultado }}</span>
        </div>
    @empty
        <p class="text-secondary mb-0">Aún no hay registros en la bitácora.</p>
    @endforelse
</div>
@endsection
