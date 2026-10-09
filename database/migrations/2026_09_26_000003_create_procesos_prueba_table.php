<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 — Registro de los procesos de prueba lanzados por la aplicación.
 * Las señales y renice SOLO se permiten sobre los PID que estén aquí
 * (regla de seguridad 3 del enunciado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_prueba', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pid')->index();
            $table->string('tipo', 30);              // sleep | carga_cpu
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos_prueba');
    }
};
