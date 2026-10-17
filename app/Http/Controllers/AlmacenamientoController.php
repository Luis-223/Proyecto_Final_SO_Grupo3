<?php

namespace App\Http\Controllers;

use App\Services\Sistema\Almacenamiento;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * M5 — Discos, particiones, sistemas de archivos, usuarios y explorador de solo lectura.
 * Fuentes: lsblk, df -h, findmnt --fstab, /etc/passwd, who.
 * Cada sección se lee por separado: si un comando falla, solo esa tarjeta muestra el error.
 */
class AlmacenamientoController extends Controller
{
    public function index(Request $request): View
    {
        $errores = [];
        $leer = function (string $seccion, callable $fn, $vacio) use (&$errores) {
            try {
                return $fn();
            } catch (Throwable $e) {
                $errores[$seccion] = $e->getMessage();

                return $vacio;
            }
        };

        $ruta = $request->query('ruta');

        return view('modulos.almacenamiento.index', [
            'bloques' => $leer('bloques', fn () => Almacenamiento::bloques(), ['filas' => [], 'tablas' => []]),
            'sistemas' => $leer('sistemas', fn () => Almacenamiento::sistemasArchivos(), []),
            'fstab' => $leer('fstab', fn () => Almacenamiento::fstab(), []),
            'usuarios' => $leer('usuarios', fn () => Almacenamiento::usuarios(), ['humanos' => [], 'sistema' => []]),
            'sesiones' => $leer('sesiones', fn () => Almacenamiento::sesiones(), []),
            'explorador' => $leer('explorador', fn () => Almacenamiento::explorar(is_string($ruta) ? $ruta : null), ['ruta' => Almacenamiento::RAIZ_EXPLORADOR, 'padre' => null, 'entradas' => []]),
            'raiz' => Almacenamiento::RAIZ_EXPLORADOR,
            'errores' => $errores,
        ]);
    }
}
