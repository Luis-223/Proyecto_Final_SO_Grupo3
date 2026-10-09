<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — Simulaciones de planificación de CPU guardadas para consultarlas después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('algoritmo', 20);          // FCFS | SJF | SRTF | RR | PRIORIDAD
            $table->unsignedInteger('quantum')->nullable();
            $table->json('procesos');                  // [{id, llegada, rafaga, prioridad}]
            $table->json('gantt');                     // [{proceso, inicio, fin}]
            $table->json('resultados');                // por proceso: espera, retorno
            $table->decimal('espera_promedio', 8, 2);
            $table->decimal('retorno_promedio', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulaciones');
    }
};
