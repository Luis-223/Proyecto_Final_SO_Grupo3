<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'accion', 'pid', 'detalle', 'resultado', 'mensaje', 'ip',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Atajo para registrar una acción desde cualquier módulo.
     */
    public static function registrar(string $accion, string $resultado, ?int $pid = null, ?string $detalle = null, ?string $mensaje = null): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'pid' => $pid,
            'detalle' => $detalle,
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'ip' => request()->ip(),
        ]);
    }
}
