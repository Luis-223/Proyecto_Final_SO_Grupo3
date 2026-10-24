<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * M6 — Cuentas de la aplicación (solo Administrador).
 *
 * El enunciado pide el registro público DESHABILITADO: por eso no hay
 * "crear cuenta" en el login. Un Administrador crea las cuentas aquí y
 * SIEMPRE con rol Observador; el rol nunca viene del formulario, así
 * nadie puede crearse un Administrador alterando la petición.
 * Cada cuenta creada queda en la bitácora.
 */
class UsuariosController extends Controller
{
    public function index(): View
    {
        return view('modulos.usuarios.index', [
            'usuarios' => User::orderBy('rol')->orderBy('name')->get(['id', 'name', 'email', 'rol', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Correo sin espacios y en minúsculas antes de validar
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                // Único sin importar mayúsculas: "Ana@..." y "ana@..." son la misma cuenta
                function (string $atributo, mixed $valor, Closure $fallar) {
                    if (User::whereRaw('LOWER(email) = ?', [$valor])->exists()) {
                        $fallar('Ya existe una cuenta con ese correo.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Escriba el nombre.',
            'email.required' => 'Escriba el correo.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Escriba la contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $usuario = User::create([
            'name' => trim($datos['name']),
            'email' => $datos['email'],
            'rol' => User::ROL_OBSERVADOR,      // fijo: nunca se toma del formulario
            'password' => $datos['password'],   // el modelo la guarda cifrada (cast "hashed")
        ]);

        Bitacora::registrar('CREAR_USUARIO', 'exito', null, "{$usuario->email} (observador)");

        return redirect()->route('usuarios.index')
            ->with('ok', "Cuenta creada para {$usuario->email} con rol Observador.");
    }
}
