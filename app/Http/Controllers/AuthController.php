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

        $usuario = DB::table('usuarios')
            ->leftJoin('roles', 'roles.idroles', '=', 'usuarios.roles_idroles')
            ->where('nombre', $credentials['usuario'])
            ->select([
                'usuarios.idusuario',
                'usuarios.nombre',
                'usuarios.estado',
                'usuarios.contrasena',
                'usuarios.roles_idroles',
                'roles.rol as rol_nombre',
            ])
            ->first();

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

        $rolNombre = is_string($usuario->rol_nombre)
            ? strtolower(trim($usuario->rol_nombre))
            : null;

        $request->session()->regenerate();
        $request->session()->put([
            'idusuario' => $usuario->idusuario,
            'usuario' => $usuario->nombre,
            'rol_id' => $usuario->roles_idroles,
            'rol_nombre' => $rolNombre,
            'roles_idroles' => $usuario->roles_idroles,
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
