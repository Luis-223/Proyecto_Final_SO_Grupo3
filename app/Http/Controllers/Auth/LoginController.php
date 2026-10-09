<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * M6 — Inicio de sesión. No existe ruta de registro (requisito del enunciado):
 * los usuarios se crean con el seeder o por un Administrador.
 */
class LoginController extends Controller
{
    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciales, $request->boolean('recordar'))) {
            Bitacora::registrar('LOGIN', 'rechazado', null, $credenciales['email'], 'Credenciales incorrectas');

            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        Bitacora::registrar('LOGIN', 'exito');

        return redirect()->intended(route('dashboard'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Bitacora::registrar('LOGOUT', 'exito');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
