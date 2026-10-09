<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Simulacion extends Model
{
    protected $table = 'simulaciones';

    protected $fillable = [
        'user_id', 'nombre', 'algoritmo', 'quantum', 'procesos', 'gantt',
        'resultados', 'espera_promedio', 'retorno_promedio',
    ];

    protected function casts(): array
    {
        return [
            'procesos' => 'array',
            'gantt' => 'array',
            'resultados' => 'array',
        ];
    }
}
