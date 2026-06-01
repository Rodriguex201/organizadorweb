@php
    $selectedRoleId = old('roles_idroles', $usuario?->roles_idroles);
    $selectedEstado = old('estado', $usuario?->estado);
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="nombre" class="mb-1 block text-sm font-medium text-slate-700">Nombre usuario *</label>
        <input
            id="nombre"
            name="nombre"
            type="text"
            value="{{ old('nombre', $usuario?->nombre) }}"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            required
        >
    </div>

    <div>
        <label for="roles_idroles" class="mb-1 block text-sm font-medium text-slate-700">Rol *</label>
        <select
            id="roles_idroles"
            name="roles_idroles"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            required
        >
            <option value="">Selecciona un rol</option>
            @foreach($roles as $rol)
                @php
                    $rolNombre = strtolower(trim((string) $rol->rol));
                    $rolLabel = (int) $rol->idroles === 1 ? 'Administrador' : 'Usuario';
                @endphp
                <option value="{{ $rol->idroles }}" @selected((string) $selectedRoleId === (string) $rol->idroles)>{{ $rolLabel }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="estado" class="mb-1 block text-sm font-medium text-slate-700">Estado *</label>
        <select
            id="estado"
            name="estado"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            required
        >
            <option value="">Selecciona un estado</option>
            <option value="1" @selected((string) $selectedEstado === '1')>Activo</option>
            <option value="0" @selected((string) $selectedEstado === '0')>Inactivo</option>
        </select>
    </div>

    <div>
        <label for="contrasena" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $isEdit ? 'Cambiar contraseña' : 'Contraseña *' }}
        </label>
        <input
            id="contrasena"
            name="contrasena"
            type="password"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @required(!$isEdit)
        >
        <p class="mt-2 text-xs text-slate-500">
            {{ $isEdit ? 'Si la dejas vacía, no se modificará la contraseña actual.' : 'La contraseña es obligatoria para crear el usuario.' }}
        </p>
    </div>

    <div>
        <label for="contrasena_confirmation" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $isEdit ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *' }}
        </label>
        <input
            id="contrasena_confirmation"
            name="contrasena_confirmation"
            type="password"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @required(!$isEdit)
        >
    </div>
</div>
