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
    $dateValue = static function (string $input, ?string $column = null) use ($value): ?string {
        return \App\Http\Controllers\ClientesController::normalizeDateForDateInput($value($input, $column));
    };

    $fieldUnavailable = $fieldUnavailable ?? static fn (?string $column): bool => $column === null;
    $normalizeSelectionValue = static function (mixed $value): string {
        $value = trim((string) $value);

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    };
    $codigoAssistEnabled = $cliente === null && !$fieldUnavailable($mapping['codigo']);
    $codigoMode = old('codigo_mode', $codigoAssistEnabled ? 'secuencia' : 'manual');

    $selectedClase = (string) $value('clase', $mapping['clase'] ?? null);
    $selectedModalidad = (string) $value('modalidad', $mapping['modalidad'] ?? null);
    $selectedLlego = (string) $value('llego', $mapping['llego'] ?? null);
    $selectedTipoCliente = (string) $value('tipo_cliente_id', $mapping['tipo_cliente'] ?? null);
    $selectedRegimen = (string) $value('regimen', $mapping['regimen'] ?? null);
    $selectedIpEmpresa = (string) $value('ip_empresa', $mapping['ip_empresa'] ?? null);
    $companyIpOptions = \App\Http\Controllers\ClientesController::companyIpOptionsForForm($selectedIpEmpresa);
    $selectedEstadoFacturacion = (string) old(
        'estado_facturacion',
        $cliente?->estado_facturacion_normalizado
            ?? $cliente?->{$mapping['estado_facturacion'] ?? 'estado_facturacion'}
            ?? \App\Models\ClientePotencial::ESTADO_FACTURACION_PENDIENTE
    );
    $selectedCity = $selectedCity ?? null;
    $selectedCityCode = old('ciudad_codigo', $selectedCity['code'] ?? '');
    $selectedCityLabel = old('departamento', $selectedCity['label'] ?? $value('departamento', $mapping['departamento']));
    $regimenOptions = ['SAS', 'PCS', 'SMP'];
    $estadosFacturacion = $estadosFacturacion ?? \App\Models\ClientePotencial::estadosFacturacion();
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <div class="grid grid-cols-4 gap-3">
            <div class="col-span-3">
                <label class="mb-1 block text-sm font-medium" for="nit">NIT</label>
                <input id="nit" name="nit" type="text" value="{{ $value('nit', $mapping['nit']) }}" @required(!$fieldUnavailable($mapping['nit'])) @disabled($fieldUnavailable($mapping['nit']))
                       class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium" for="dv">DV</label>
                <input id="dv" name="dv" type="text" value="{{ $value('dv', $mapping['dv']) }}" maxlength="3" @required(!$fieldUnavailable($mapping['dv'])) @disabled($fieldUnavailable($mapping['dv']))
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
        <label class="mb-1 block text-sm font-medium" for="nombre">Nombre Responsable</label>
        <input id="nombre" name="nombre" type="text" value="{{ $value('nombre', $mapping['nombre']) }}" @required(!$fieldUnavailable($mapping['nombre'])) @disabled($fieldUnavailable($mapping['nombre']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
        <p class="mt-1 text-xs text-slate-500">Nombre de la persona responsable o contacto principal de la empresa.</p>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="codigo">Codigo</label>
        <input id="codigo" name="codigo" type="text" value="{{ $value('codigo', $mapping['codigo']) }}" @required(!$fieldUnavailable($mapping['codigo'])) @disabled($fieldUnavailable($mapping['codigo']))
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
        @endif
        <p id="codigo_estado" class="mt-1 text-xs text-slate-500"></p>
        @error('codigo')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" value="{{ $value('empresa', $mapping['empresa']) }}" @required(!$fieldUnavailable($mapping['empresa'])) @disabled($fieldUnavailable($mapping['empresa']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="celular1">Celular</label>
        <input id="celular1" name="celular1" type="text" value="{{ $value('celular1', $mapping['celular1']) }}" @required(!$fieldUnavailable($mapping['celular1'])) @disabled($fieldUnavailable($mapping['celular1']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="email">Email</label>
        <input id="email" name="email" type="text" inputmode="email" autocomplete="email" value="{{ $value('email', $mapping['email']) }}" @required(!$fieldUnavailable($mapping['email'])) @disabled($fieldUnavailable($mapping['email']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
        <p class="mt-1 text-xs text-slate-500">Puede ingresar varios correos separados por coma o punto y coma. Ejemplo: correo1@empresa.com, correo2@empresa.com</p>
        @error('email')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium" for="ciudad_busqueda">Ciudad / Departamento</label>

        <div class="flex items-stretch gap-2">
            <input
                id="ciudad_busqueda"
                type="text"
                value="{{ $selectedCityLabel }}"
                placeholder="Ej: Med"
                @required(!$fieldUnavailable($mapping['departamento']))
                @disabled($fieldUnavailable($mapping['departamento']))
                class="w-full rounded border border-slate-300 px-3 py-2"
            >
            <button
                type="button"
                id="ciudad_buscar_btn"
                @disabled($fieldUnavailable($mapping['departamento']))
                class="inline-flex items-center justify-center rounded border border-slate-300 bg-slate-100 px-3 py-2 text-slate-700 hover:bg-slate-200"
                aria-label="Buscar ciudad"
                title="Buscar ciudad"
            >
                Buscar
            </button>
        </div>

        <input type="hidden" name="departamento" id="departamento" value="{{ $selectedCityLabel }}">
        <input type="hidden" name="ciudad_codigo" id="ciudad_codigo" value="{{ $selectedCityCode }}">

        <p id="ciudad_estado" class="mt-2 text-xs text-slate-500"></p>
        <div id="ciudad_resultados" class="mt-2 hidden rounded border border-slate-200 bg-white"></div>
        @error('departamento')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @error('ciudad_codigo')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="fecha_inicio">Fecha inicio</label>
        <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $dateValue('fecha_inicio', $mapping['fecha_llegada']) }}" @required(!$fieldUnavailable($mapping['fecha_llegada'])) @disabled($fieldUnavailable($mapping['fecha_llegada']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="fecha_arriendo">Fecha arriendo</label>
        <input id="fecha_arriendo" name="fecha_arriendo" type="date" value="{{ $dateValue('fecha_arriendo', $mapping['fecha_arriendo']) }}" @required(!$fieldUnavailable($mapping['fecha_arriendo'])) @disabled($fieldUnavailable($mapping['fecha_arriendo']))
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="ip_empresa">IP empresa</label>
        <select id="ip_empresa" name="ip_empresa" @required(!$fieldUnavailable($mapping['ip_empresa'] ?? null)) @disabled($fieldUnavailable($mapping['ip_empresa'] ?? null))
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($companyIpOptions as $ipOption)
                <option value="{{ $ipOption }}" @selected($selectedIpEmpresa === $ipOption)>
                    {{ \App\Http\Controllers\ClientesController::isAllowedCompanyIp($ipOption) ? $ipOption : 'IP historica: '.$ipOption }}
                </option>
            @endforeach
        </select>
        @error('ip_empresa')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="clase">Clase</label>
        <select id="clase" name="clase" @required(!$fieldUnavailable($mapping['clase']) && $clases !== []) @disabled($fieldUnavailable($mapping['clase']) || $clases === [])
               class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($clases as $opcion)
                <option value="{{ $opcion['id'] }}" @selected(
                    $selectedClase === (string) $opcion['id']
                    || $normalizeSelectionValue($selectedClase) === $normalizeSelectionValue($opcion['label'])
                )>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="modalidad">Modalidad</label>
        <select id="modalidad" name="modalidad" @required(!$fieldUnavailable($mapping['modalidad']) && $modalidades !== []) @disabled($fieldUnavailable($mapping['modalidad']) || $modalidades === [])
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($modalidades as $opcion)
                <option value="{{ $opcion['id'] }}" @selected(
                    $selectedModalidad === (string) $opcion['id']
                    || $normalizeSelectionValue($selectedModalidad) === $normalizeSelectionValue($opcion['label'])
                )>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="regimen">Regimen</label>
        <select id="regimen" name="regimen" @required(!$fieldUnavailable($mapping['regimen'] ?? null)) @disabled($fieldUnavailable($mapping['regimen'] ?? null))
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($regimenOptions as $opcion)
                <option value="{{ $opcion }}" @selected($selectedRegimen === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
        @error('regimen')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="tipo_cliente_id">Tipo de cliente</label>
        <select id="tipo_cliente_id" name="tipo_cliente_id" @required(!$fieldUnavailable($mapping['tipo_cliente'] ?? null) && $tiposCliente !== []) @disabled($fieldUnavailable($mapping['tipo_cliente'] ?? null) || $tiposCliente === [])
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
        <select id="llego" name="llego" @required(!$fieldUnavailable($mapping['llego']) && $llegos !== []) @disabled($fieldUnavailable($mapping['llego']) || $llegos === [])
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opcion</option>
            @foreach($llegos as $opcion)
                <option value="{{ $opcion['id'] }}" @selected(
                    $selectedLlego === (string) $opcion['id']
                    || $normalizeSelectionValue($selectedLlego) === $normalizeSelectionValue($opcion['label'])
                )>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium" for="estado_facturacion">Estado Facturacion</label>
        <select id="estado_facturacion" name="estado_facturacion" @required(!$fieldUnavailable($mapping['estado_facturacion'] ?? null)) @disabled($fieldUnavailable($mapping['estado_facturacion'] ?? null))
                class="w-full rounded border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            @foreach($estadosFacturacion as $estadoFacturacion)
                <option value="{{ $estadoFacturacion }}" @selected($selectedEstadoFacturacion === $estadoFacturacion)>{{ $estadoFacturacion }}</option>
            @endforeach
        </select>
        @error('estado_facturacion')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>
