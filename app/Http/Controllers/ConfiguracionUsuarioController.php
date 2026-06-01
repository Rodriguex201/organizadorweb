<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfiguracionUsuarioController extends Controller
{
    private const ADMIN_ROLE_ID = 1;

    public function index(Request $request): View
    {
        $this->assertAdminAccess();

        $validated = Validator::make($request->query(), [
            'nombre' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', 'integer', Rule::exists('roles', 'idroles')],
            'estado' => ['nullable', 'in:0,1'],
        ])->validate();

        $filters = [
            'nombre' => trim((string) ($validated['nombre'] ?? '')),
            'rol' => isset($validated['rol']) ? (int) $validated['rol'] : null,
            'estado' => isset($validated['estado']) && $validated['estado'] !== ''
                ? (string) $validated['estado']
                : null,
        ];

        $usuarios = Usuario::query()
            ->with('rol')
            ->when($filters['nombre'] !== '', function ($query) use ($filters) {
                $query->where('nombre', 'like', '%'.$filters['nombre'].'%');
            })
            ->when($filters['rol'] !== null, function ($query) use ($filters) {
                $query->where('roles_idroles', $filters['rol']);
            })
            ->when($filters['estado'] !== null, function ($query) use ($filters) {
                $query->where('estado', (int) $filters['estado']);
            })
            ->orderBy('idusuario')
            ->paginate(15)
            ->withQueryString();

        return view('configuracion.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => $this->availableRoles(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->assertAdminAccess();

        return view('configuracion.usuarios.create', [
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertAdminAccess();

        $validated = $this->validateUsuarioRequest($request, null);
        $nombre = trim((string) $validated['nombre']);

        Usuario::query()->create([
            'nombre' => $nombre,
            'contrasena' => md5((string) $validated['contrasena']),
            'roles_idroles' => (int) $validated['roles_idroles'],
            'estado' => (int) $validated['estado'],
        ]);

        return redirect()
            ->route('configuracion.usuarios.index')
            ->with('status', 'Usuario creado correctamente.')
            ->with('status_type', 'success');
    }

    public function edit(Usuario $usuario): View
    {
        $this->assertAdminAccess();

        return view('configuracion.usuarios.edit', [
            'usuario' => $usuario->load('rol'),
            'roles' => $this->availableRoles(),
        ]);
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $this->assertAdminAccess();

        $validated = $this->validateUsuarioRequest($request, $usuario);
        $nombre = trim((string) $validated['nombre']);
        $nuevoRolId = (int) $validated['roles_idroles'];
        $nuevoEstado = (int) $validated['estado'];

        $lastAdminError = $this->guardLastActiveAdmin($usuario, $nuevoRolId, $nuevoEstado);
        if ($lastAdminError !== null) {
            return back()
                ->withInput()
                ->withErrors(['estado' => $lastAdminError]);
        }

        $usuario->nombre = $nombre;
        $usuario->roles_idroles = $nuevoRolId;
        $usuario->estado = $nuevoEstado;

        $newPassword = (string) ($validated['contrasena'] ?? '');
        if ($newPassword !== '') {
            $usuario->contrasena = md5($newPassword);
        }

        $usuario->save();

        if ((int) session('idusuario') === (int) $usuario->idusuario) {
            if ($usuario->estado !== 1) {
                $request->session()->forget(['idusuario', 'usuario', 'rol_id', 'rol_nombre', 'roles_idroles', 'rol']);
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login');
            }

            $rolNombre = strtolower(trim((string) ($usuario->rol()->value('rol') ?? 'sin rol')));
            $request->session()->put([
                'usuario' => $usuario->nombre,
                'rol_id' => $usuario->roles_idroles,
                'roles_idroles' => $usuario->roles_idroles,
                'rol_nombre' => $rolNombre,
                'rol' => $rolNombre,
            ]);

            if ((int) $usuario->roles_idroles !== self::ADMIN_ROLE_ID) {
                return redirect('/')
                    ->with('status', 'Tu usuario fue actualizado y ya no tiene acceso al módulo de configuración.')
                    ->with('status_type', 'success');
            }
        }

        return redirect()
            ->route('configuracion.usuarios.index')
            ->with('status', 'Usuario actualizado correctamente.')
            ->with('status_type', 'success');
    }

    private function validateUsuarioRequest(Request $request, ?Usuario $usuario): array
    {
        $isUpdate = $usuario !== null;

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:100'],
            'roles_idroles' => ['required', 'integer', Rule::exists('roles', 'idroles')],
            'estado' => ['required', 'in:0,1'],
            'contrasena' => $isUpdate
                ? ['nullable', 'string', 'confirmed']
                : ['required', 'string', 'confirmed'],
        ], [
            'nombre.required' => 'El nombre de usuario es obligatorio.',
            'roles_idroles.required' => 'El rol es obligatorio.',
            'roles_idroles.exists' => 'Selecciona un rol válido.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'Selecciona un estado válido.',
            'contrasena.required' => 'La contraseña es obligatoria.',
            'contrasena.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $validator->after(function ($validator) use ($request, $usuario, $isUpdate) {
            $nombre = trim((string) $request->input('nombre', ''));
            $password = (string) $request->input('contrasena', '');

            if ($nombre === '') {
                $validator->errors()->add('nombre', 'El nombre de usuario es obligatorio.');
            }

            if (!$isUpdate && trim($password) === '') {
                $validator->errors()->add('contrasena', 'La contraseña no puede estar vacía al crear el usuario.');
            }

            $duplicateQuery = Usuario::query()
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)]);

            if ($usuario) {
                $duplicateQuery->where('idusuario', '!=', $usuario->idusuario);
            }

            if ($nombre !== '' && $duplicateQuery->exists()) {
                $validator->errors()->add('nombre', 'Ya existe un usuario con ese nombre.');
            }
        });

        return $validator->validate();
    }

    private function guardLastActiveAdmin(Usuario $usuario, int $nuevoRolId, int $nuevoEstado): ?string
    {
        $wasActiveAdmin = (int) $usuario->roles_idroles === self::ADMIN_ROLE_ID && (int) $usuario->estado === 1;
        $willRemainActiveAdmin = $nuevoRolId === self::ADMIN_ROLE_ID && $nuevoEstado === 1;

        if (!$wasActiveAdmin || $willRemainActiveAdmin) {
            return null;
        }

        $otherActiveAdmins = Usuario::query()
            ->where('roles_idroles', self::ADMIN_ROLE_ID)
            ->where('estado', 1)
            ->where('idusuario', '!=', $usuario->idusuario)
            ->count();

        if ($otherActiveAdmins === 0) {
            return 'No puedes dejar el sistema sin al menos un administrador activo.';
        }

        return null;
    }

    private function availableRoles()
    {
        return Rol::query()
            ->orderByRaw('CASE WHEN idroles = '.self::ADMIN_ROLE_ID.' THEN 0 ELSE 1 END')
            ->orderBy('rol')
            ->get();
    }

    private function assertAdminAccess(): void
    {
        abort_unless(
            (int) session('rol_id', session('roles_idroles')) === self::ADMIN_ROLE_ID,
            403,
            'Esta sección es solo para administradores.'
        );
    }
}
