<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcesoPrueba extends Model
{
    protected $table = 'procesos_prueba';

    protected $fillable = ['pid', 'tipo', 'user_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
