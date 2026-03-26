@php
    $cliente = $cliente ?? null;

    $value = static function (string $input, ?string $column = null) use ($cliente) {
        $fallback = $column && $cliente ? ($cliente->{$column} ?? null) : null;

        return old($input, $fallback);
    };

    $fieldUnavailable = static fn (?string $column): bool => $column === null;
@endphp

@if($errors->has('general'))
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ $errors->first('general') }}
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1" for="nit">NIT</label>
        <input id="nit" name="nit" type="text" value="{{ $value('nit', $mapping['nit']) }}" @disabled($fieldUnavailable($mapping['nit']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="dv">DV</label>
        <input id="dv" name="dv" type="text" value="{{ $value('dv', $mapping['dv']) }}" @disabled($fieldUnavailable($mapping['dv']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ $value('nombre', $mapping['nombre']) }}" @disabled($fieldUnavailable($mapping['nombre']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="codigo">Código</label>
        <input id="codigo" name="codigo" type="text" value="{{ $value('codigo', $mapping['codigo']) }}" @disabled($fieldUnavailable($mapping['codigo']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" value="{{ $value('empresa', $mapping['empresa']) }}" @disabled($fieldUnavailable($mapping['empresa']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="correo">Correo</label>
        <input id="correo" name="correo" type="email" value="{{ $value('correo', $mapping['correo']) }}" @disabled($fieldUnavailable($mapping['correo']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" type="text" value="{{ $value('telefono', $mapping['telefono']) }}" @disabled($fieldUnavailable($mapping['telefono']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="contacto">Contacto</label>
        <input id="contacto" name="contacto" type="text" value="{{ $value('contacto', $mapping['contacto']) }}" @disabled($fieldUnavailable($mapping['contacto']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1" for="ciudad_busqueda">Ciudad / Departamento</label>

        <div class="flex items-stretch gap-2">
            <input
                id="ciudad_busqueda"
                type="text"
                value="{{ $value('departamento', $mapping['departamento']) }}"
                placeholder="Ej: Med"
                class="w-full border border-slate-300 rounded px-3 py-2"
            >
            <button
                type="button"
                id="ciudad_buscar_btn"
                class="inline-flex items-center justify-center rounded border border-slate-300 bg-slate-100 px-3 py-2 text-slate-700 hover:bg-slate-200"
                aria-label="Buscar ciudad"
                title="Buscar ciudad"
            >
                🔍
            </button>
        </div>

        <input type="hidden" name="departamento" id="departamento" value="{{ $value('departamento', $mapping['departamento']) }}">

        <p id="ciudad_estado" class="mt-2 text-xs text-slate-500"></p>
        <div id="ciudad_resultados" class="mt-2 hidden rounded border border-slate-200 bg-white"></div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="fecha_inicio">Fecha inicio</label>
        <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $value('fecha_inicio', $mapping['fecha_inicio']) }}" @disabled($fieldUnavailable($mapping['fecha_inicio']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="fecha_arriendo">Fecha arriendo</label>
        <input id="fecha_arriendo" name="fecha_arriendo" type="date" value="{{ $value('fecha_arriendo', $mapping['fecha_arriendo']) }}" @disabled($fieldUnavailable($mapping['fecha_arriendo']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="fecha_cotizacion">Fecha cotización</label>
        <input id="fecha_cotizacion" name="fecha_cotizacion" type="date" value="{{ $value('fecha_cotizacion', $mapping['fecha_cotizacion']) }}" @disabled($fieldUnavailable($mapping['fecha_cotizacion']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="contrato">Contrato / Modalidad</label>
        <input id="contrato" name="contrato" type="text" value="{{ $value('contrato', $mapping['contrato']) }}" @disabled($fieldUnavailable($mapping['contrato']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>
</div>

<p class="mt-4 text-xs text-slate-500">Los campos deshabilitados no existen aún en la tabla <code>clientes_potenciales</code> de esta instancia y se muestran como fallback visual.</p>

@once
    @push('scripts')
        <script>
            (() => {
                const inputBusqueda = document.getElementById('ciudad_busqueda');
                const botonBuscar = document.getElementById('ciudad_buscar_btn');
                const inputDepartamento = document.getElementById('departamento');
                const estado = document.getElementById('ciudad_estado');
                const resultados = document.getElementById('ciudad_resultados');

                if (!inputBusqueda || !botonBuscar || !inputDepartamento || !estado || !resultados) {
                    return;
                }

                const setEstado = (texto, error = false) => {
                    estado.textContent = texto;
                    estado.classList.toggle('text-rose-600', error);
                    estado.classList.toggle('text-slate-500', !error);
                };

                const limpiarResultados = () => {
                    resultados.innerHTML = '';
                    resultados.classList.add('hidden');
                };

                const pintarResultados = (items) => {
                    limpiarResultados();

                    if (!items.length) {
                        setEstado('No se encontraron ciudades.');
                        return;
                    }

                    resultados.classList.remove('hidden');
                    setEstado('Selecciona una ciudad de la lista.');

                    items.forEach((item) => {
                        const opcion = document.createElement('button');
                        opcion.type = 'button';
                        opcion.className = 'block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-slate-50 last:border-b-0';
                        opcion.textContent = item.label;

                        opcion.addEventListener('click', () => {
                            inputBusqueda.value = item.label;
                            inputDepartamento.value = item.label;
                            setEstado('Ciudad seleccionada.');
                            limpiarResultados();
                        });

                        resultados.appendChild(opcion);
                    });
                };


                inputBusqueda.addEventListener('input', () => {
                    inputDepartamento.value = inputBusqueda.value.trim();
                    limpiarResultados();
                    setEstado('Usa la lupa para buscar y seleccionar una ciudad.');
                });

                botonBuscar.addEventListener('click', async () => {
                    const termino = inputBusqueda.value.trim();

                    inputDepartamento.value = termino;

                    if (termino.length < 3) {
                        limpiarResultados();
                        setEstado('Escribe al menos 3 caracteres para buscar.', true);
                        return;
                    }

                    setEstado('Buscando ciudades...');
                    limpiarResultados();

                    try {
                        const response = await fetch(`{{ route('ciudades.buscar') }}?q=${encodeURIComponent(termino)}`, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            setEstado(payload.message ?? 'No fue posible completar la búsqueda.', true);
                            return;
                        }

                        pintarResultados(payload.results ?? []);
                    } catch (error) {
                        setEstado('Error consultando ciudades. Intenta de nuevo.', true);
                    }
                });
            })();
        </script>
    @endpush
@endonce
