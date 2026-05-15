@php
    $cliente = $cliente ?? null;
    $catalogos = $catalogos ?? [];
    $clases = $catalogos['clases']['options'] ?? [];
    $modalidades = $catalogos['modalidad']['options'] ?? [];
    $llegos = $catalogos['llego']['options'] ?? [];
    $tiposCliente = $catalogos['tipos_cliente']['options'] ?? [];

    $value = $value ?? static function (string $input, ?string $column = null) use ($cliente) {
        $fallback = $column && $cliente ? ($cliente->{$column} ?? null) : null;

        return old($input, $fallback);
    };

    $fieldUnavailable = $fieldUnavailable ?? static fn (?string $column): bool => $column === null;
    $codigoAssistEnabled = $cliente === null && !$fieldUnavailable($mapping['codigo']);
    $codigoMode = old('codigo_mode', $codigoAssistEnabled ? 'secuencia' : 'manual');

    $selectedClase = (string) $value('clase', $mapping['clase'] ?? null);
    $selectedModalidad = (string) $value('modalidad', $mapping['modalidad'] ?? null);
    $selectedLlego = (string) $value('llego', $mapping['llego'] ?? null);
    $selectedTipoCliente = (string) $value('tipo_cliente_id', $mapping['tipo_cliente'] ?? null);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <div class="grid grid-cols-4 gap-3">
            <div class="col-span-3">
                <label class="mb-1 block text-sm font-medium" for="nit">NIT</label>
                <input id="nit" name="nit" type="text" value="{{ $value('nit', $mapping['nit']) }}" @disabled($fieldUnavailable($mapping['nit']))
                       class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium" for="dv">DV</label>
                <input id="dv" name="dv" type="text" value="{{ $value('dv', $mapping['dv']) }}" maxlength="3" @disabled($fieldUnavailable($mapping['dv']))
                       class="w-full rounded border border-slate-300 px-3 py-2 uppercase disabled:bg-slate-100">
            </div>
        </div>

        @error('nit')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @error('dv')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ $value('nombre', $mapping['nombre']) }}" @disabled($fieldUnavailable($mapping['nombre']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="codigo">Codigo</label>
        <input id="codigo" name="codigo" type="text" value="{{ $value('codigo', $mapping['codigo']) }}" @disabled($fieldUnavailable($mapping['codigo']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
        @if($codigoAssistEnabled)
            <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="codigo_mode" value="secuencia" class="text-indigo-600 focus:ring-indigo-500" @checked($codigoMode === 'secuencia')>
                    <span>Continuar secuencia</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="codigo_mode" value="manual" class="text-indigo-600 focus:ring-indigo-500" @checked($codigoMode === 'manual')>
                    <span>Escribir manualmente</span>
                </label>
            </div>
            <p id="codigo_modo_estado" class="mt-2 text-xs text-slate-500">
                {{ $codigoMode === 'secuencia' ? 'Usa el codigo actual como referencia y se completara el siguiente consecutivo.' : 'Puedes escribir el codigo libremente. La disponibilidad se valida en tiempo real.' }}
            </p>
            <p id="codigo_estado" class="mt-1 text-xs text-slate-500"></p>
        @endif
        @error('codigo')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" value="{{ $value('empresa', $mapping['empresa']) }}" @disabled($fieldUnavailable($mapping['empresa']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="celular1">Celular</label>
        <input id="celular1" name="celular1" type="text" value="{{ $value('celular1', $mapping['celular1']) }}" @disabled($fieldUnavailable($mapping['celular1']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ $value('email', $mapping['email']) }}" @disabled($fieldUnavailable($mapping['email']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium" for="ciudad_busqueda">Ciudad / Departamento</label>

        <div class="flex items-stretch gap-2">
            <input
                id="ciudad_busqueda"
                type="text"
                value="{{ $value('departamento', $mapping['departamento']) }}"
                placeholder="Ej: Med"
                class="w-full rounded border border-slate-300 px-3 py-2"
            >
            <button
                type="button"
                id="ciudad_buscar_btn"
                class="inline-flex items-center justify-center rounded border border-slate-300 bg-slate-100 px-3 py-2 text-slate-700 hover:bg-slate-200"
                aria-label="Buscar ciudad"
                title="Buscar ciudad"
            >
                Buscar
            </button>
        </div>

        <input type="hidden" name="departamento" id="departamento" value="{{ $value('departamento', $mapping['departamento']) }}">

        <p id="ciudad_estado" class="mt-2 text-xs text-slate-500"></p>
        <div id="ciudad_resultados" class="mt-2 hidden rounded border border-slate-200 bg-white"></div>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="fecha_inicio">Fecha inicio</label>
        <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $value('fecha_inicio', $mapping['fecha_llegada']) }}" @disabled($fieldUnavailable($mapping['fecha_llegada']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="fecha_arriendo">Fecha arriendo</label>
        <input id="fecha_arriendo" name="fecha_arriendo" type="date" value="{{ $value('fecha_arriendo', $mapping['fecha_arriendo']) }}" @disabled($fieldUnavailable($mapping['fecha_arriendo']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="ip_empresa">IP empresa</label>
        <input id="ip_empresa" name="ip_empresa" type="text" value="{{ $value('ip_empresa', $mapping['ip_empresa'] ?? null) }}" @disabled($fieldUnavailable($mapping['ip_empresa'] ?? null))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="clase">Clase</label>
        <select id="clase" name="clase" @disabled($fieldUnavailable($mapping['clase']) || $clases === [])
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($clases as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedClase === (string) $opcion['id'] || $selectedClase === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="modalidad">Modalidad</label>
        <select id="modalidad" name="modalidad" @disabled($fieldUnavailable($mapping['modalidad']) || $modalidades === [])
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($modalidades as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedModalidad === (string) $opcion['id'] || $selectedModalidad === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="tipo_cliente_id">Tipo de cliente</label>
        <select id="tipo_cliente_id" name="tipo_cliente_id" @disabled($fieldUnavailable($mapping['tipo_cliente'] ?? null) || $tiposCliente === [])
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($tiposCliente as $opcion)
                <option value="{{ $opcion['id'] }}" data-tipo-cliente-label="{{ \Illuminate\Support\Str::lower($opcion['label']) }}" @selected($selectedTipoCliente === (string) $opcion['id'] || $selectedTipoCliente === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
        @error('tipo_cliente_id')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="llego">Como llego</label>
        <select id="llego" name="llego" @disabled($fieldUnavailable($mapping['llego']) || $llegos === [])
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($llegos as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedLlego === (string) $opcion['id'] || $selectedLlego === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>
</div>
