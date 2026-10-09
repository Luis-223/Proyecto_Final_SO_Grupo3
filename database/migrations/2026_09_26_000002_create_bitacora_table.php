<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M6 — Bitácora de acciones administrativas.
 * Cada señal, renice o lanzamiento de proceso de prueba deja un registro:
 * usuario, fecha y hora, acción, proceso afectado y resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accion', 50);            // ej. SIGTERM, SIGKILL, RENICE, LANZAR_PRUEBA, LOGIN
            $table->unsignedInteger('pid')->nullable(); // proceso afectado
            $table->string('detalle')->nullable();   // ej. "nice 0 → 10", "sleep 300"
            $table->string('resultado', 20);         // exito | error | rechazado
            $table->string('mensaje')->nullable();   // salida o motivo del error
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
