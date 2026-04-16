<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('idusuario')) {
            return redirect('/');
        }

        return view('auth.login');
    }

public function login(Request $request): RedirectResponse
{
    $credentials = $request->validate([
        'usuario' => ['required', 'string'],
        'clave' => ['required', 'string'],
    ], [
        'usuario.required' => 'El usuario es obligatorio.',
        'clave.required' => 'La contraseña es obligatoria.',
    ]);

    // 🔹 Buscar usuario
    $usuario = DB::table('usuarios')
        ->where('nombre', $credentials['usuario'])
        ->first();

    // 🔹 Validar credenciales
    if (
        !$usuario
        || (int) ($usuario->estado ?? 0) !== 1
        || $usuario->contrasena !== md5($credentials['clave'])
    ) {
        return back()
            ->withInput($request->except('clave'))
            ->withErrors([
                'usuario' => 'Credenciales inválidas o usuario inactivo.',
            ]);
    }

    // 🔹 Buscar rol
    $rol = DB::table('roles')
        ->where('idroles', $usuario->roles_idroles)
        ->first();

    // 🔹 Nombre del rol seguro
    $rolNombre = $rol && isset($rol->rol)
        ? strtolower(trim($rol->rol))
        : 'sin rol';

    // 🔹 Guardar sesión
    $request->session()->regenerate();

    $request->session()->put([
        'idusuario' => $usuario->idusuario,
        'usuario' => $usuario->nombre,
        'rol_id' => $usuario->roles_idroles,
        'rol_nombre' => $rolNombre,
        'rol' => $rolNombre,
    ]);

    return redirect('/');
}

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['idusuario', 'usuario', 'rol_id', 'rol_nombre', 'roles_idroles', 'rol']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
