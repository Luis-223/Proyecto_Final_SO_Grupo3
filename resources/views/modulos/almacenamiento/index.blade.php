@extends('layouts.app')

@section('titulo', 'Almacenamiento')
@section('modulo', 'M5 · lsblk · df -h · findmnt · /etc/passwd · who')

@section('contenido')
<div class="row g-3 mb-3">
    {{-- Discos y particiones --}}
    <div class="col-xl-6">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Discos y particiones</h2>
                <span class="badge text-bg-light border sm-mono" id="tabla-particiones">Tabla: —</span>
            </div>
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Tamaño</th><th>Tipo</th><th>Sistema de archivos</th><th>Montaje</th></tr></thead>
                <tbody id="bloques">
                    <tr><td colspan="5" class="sm-vacio">No hay discos para mostrar.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Uso de sistemas de archivos --}}
    <div class="col-xl-6"><div class="sm-card h-100 d-flex flex-column">
        <h2>Sistemas de archivos montados</h2>
        <div id="uso-fs" class="flex-grow-1">
            <div class="sm-area-vacia" style="min-height: 120px">No hay sistemas de archivos para mostrar.</div>
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
        <span class="sm-fuente">/etc/fstab</span>
    </div>
    <table class="table align-middle">
        <thead><tr><th>Dispositivo</th><th>Montaje</th><th>Tipo</th><th>Opciones</th><th>dump / pass</th></tr></thead>
        <tbody id="fstab">
            <tr><td colspan="5" class="sm-vacio">No hay puntos de montaje para mostrar.</td></tr>
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
                    <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="tab" data-bs-target="#usuarios-humanos" type="button">Humanos</button></li>
                    <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#usuarios-sistema" type="button">Sistema</button></li>
                </ul>
            </div>
            <div class="tab-content">
                @foreach (['usuarios-humanos' => true, 'usuarios-sistema' => false] as $id => $activo)
                    <div class="tab-pane fade {{ $activo ? 'show active' : '' }}" id="{{ $id }}">
                        <table class="table align-middle">
                            <thead><tr><th>Usuario</th><th>UID</th><th>Home</th><th>Shell</th></tr></thead>
                            <tbody><tr><td colspan="4" class="sm-vacio">No hay usuarios para mostrar.</td></tr></tbody>
                        </table>
                    </div>
                @endforeach
            </div>
            <div class="px-3 pb-3" style="font-size: 12px; color: #5f6965">Humano: UID ≥ 1000 y shell de login. Sistema: UID &lt; 1000 o shell nologin.</div>
        </div>

        <div class="sm-card sm-card-tabla">
            <div class="sm-card-head d-flex justify-content-between align-items-baseline">
                <h2 class="mb-0">Sesiones activas</h2>
                <span class="sm-fuente">who</span>
            </div>
            <table class="table align-middle">
                <thead><tr><th>Usuario</th><th>Terminal</th><th>Desde</th><th>Origen</th></tr></thead>
                <tbody id="sesiones"><tr><td colspan="4" class="sm-vacio">No hay sesiones para mostrar.</td></tr></tbody>
            </table>
        </div>
    </div>

    {{-- Explorador de solo lectura --}}
    <div class="col-xl-7">
        <div class="sm-card sm-card-tabla h-100">
            <div class="sm-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="mb-0">Explorador (solo lectura)</h2>
                <nav aria-label="Ruta" class="sm-mono" id="ruta-actual">/var/www</nav>
            </div>
            <table class="table align-middle">
                <thead><tr><th>Permisos</th><th>Propietario</th><th>Grupo</th><th>Tamaño</th><th>Nombre</th></tr></thead>
                <tbody id="explorador"><tr><td colspan="5" class="sm-vacio">No hay archivos para mostrar.</td></tr></tbody>
            </table>
            <div class="px-3 pb-3" style="font-size: 12px; color: #5f6965">Solo se permite navegar dentro de la carpeta raíz configurada. No se abre ni se descarga contenido.</div>
        </div>
    </div>
</div>
@endsection
