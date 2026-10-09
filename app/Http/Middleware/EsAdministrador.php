<?php

namespace App\Http\Middleware;

use App\Models\Bitacora;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite el paso solo a usuarios con rol Administrador.
 * Los intentos rechazados también quedan en la bitácora.
 */
class EsAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->esAdministrador()) {
            Bitacora::registrar('ACCESO_DENEGADO', 'rechazado', null, $request->path());

            abort(403, 'Esta acción requiere rol Administrador.');
        }

        return $next($request);
    }
}
