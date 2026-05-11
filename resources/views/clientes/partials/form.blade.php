@php
    $cliente = $cliente ?? null;
    $catalogos = $catalogos ?? [];
    $clases = $catalogos['clases']['options'] ?? [];
    $modalidades = $catalogos['modalidad']['options'] ?? [];
    $llegos = $catalogos['llego']['options'] ?? [];

    $value = static function (string $input, ?string $column = null) use ($cliente) {
        $fallback = $column && $cliente ? ($cliente->{$column} ?? null) : null;

        return old($input, $fallback);
    };

    $fieldUnavailable = static fn (?string $column): bool => $column === null;
    $codigoAssistEnabled = $cliente === null && !$fieldUnavailable($mapping['codigo']);
    $codigoMode = old('codigo_mode', $codigoAssistEnabled ? 'secuencia' : 'manual');

    $selectedClase = (string) $value('clase', $mapping['clase'] ?? null);
    $selectedModalidad = (string) $value('modalidad', $mapping['modalidad'] ?? null);
    $selectedLlego = (string) $value('llego', $mapping['llego'] ?? null);
@endphp

@if($errors->has('general'))
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ $errors->first('general') }}
    </div>
@endif

@if($errors->any() && !$errors->has('general'))
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-medium">Revisa los campos del formulario:</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <div class="grid grid-cols-4 gap-3">
            <div class="col-span-3">
                <label class="block text-sm font-medium mb-1" for="nit">NIT</label>
                <input id="nit" name="nit" type="text" value="{{ $value('nit', $mapping['nit']) }}" @disabled($fieldUnavailable($mapping['nit']))
                       class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="dv">DV</label>
                <input id="dv" name="dv" type="text" value="{{ $value('dv', $mapping['dv']) }}" maxlength="3" @disabled($fieldUnavailable($mapping['dv']))
                       class="w-full border border-slate-300 rounded px-3 py-2 uppercase disabled:bg-slate-100">
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
        <label class="block text-sm font-medium mb-1" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ $value('nombre', $mapping['nombre']) }}" @disabled($fieldUnavailable($mapping['nombre']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="codigo">Código</label>
        <input id="codigo" name="codigo" type="text" value="{{ $value('codigo', $mapping['codigo']) }}" @disabled($fieldUnavailable($mapping['codigo']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
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
                {{ $codigoMode === 'secuencia' ? 'Usa el código actual como referencia y se completará el siguiente consecutivo.' : 'Puedes escribir el código libremente. La disponibilidad se valida en tiempo real.' }}
            </p>
            <p id="codigo_estado" class="mt-1 text-xs text-slate-500"></p>
        @endif
        @error('codigo')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" value="{{ $value('empresa', $mapping['empresa']) }}" @disabled($fieldUnavailable($mapping['empresa']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="celular1">Celular</label>
        <input id="celular1" name="celular1" type="text" value="{{ $value('celular1', $mapping['celular1']) }}" @disabled($fieldUnavailable($mapping['celular1']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ $value('email', $mapping['email']) }}" @disabled($fieldUnavailable($mapping['email']))
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
        <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $value('fecha_inicio', $mapping['fecha_llegada']) }}" @disabled($fieldUnavailable($mapping['fecha_llegada']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="fecha_arriendo">Fecha arriendo</label>
        <input id="fecha_arriendo" name="fecha_arriendo" type="date" value="{{ $value('fecha_arriendo', $mapping['fecha_arriendo']) }}" @disabled($fieldUnavailable($mapping['fecha_arriendo']))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="ip_empresa">IP empresa</label>
        <input id="ip_empresa" name="ip_empresa" type="text" value="{{ $value('ip_empresa', $mapping['ip_empresa'] ?? null) }}" @disabled($fieldUnavailable($mapping['ip_empresa'] ?? null))
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="clase">Clase</label>
        <select id="clase" name="clase" @disabled($fieldUnavailable($mapping['clase']) || $clases === [])
               class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opción</option>
            @foreach($clases as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedClase === (string) $opcion['id'] || $selectedClase === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="modalidad">Modalidad</label>
        <select id="modalidad" name="modalidad" @disabled($fieldUnavailable($mapping['modalidad']) || $modalidades === [])
                class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opción</option>
            @foreach($modalidades as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedModalidad === (string) $opcion['id'] || $selectedModalidad === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="llego">Cómo llegó</label>
        <select id="llego" name="llego" @disabled($fieldUnavailable($mapping['llego']) || $llegos === [])
                class="w-full border border-slate-300 rounded px-3 py-2 disabled:bg-slate-100">
            <option value="">Selecciona una opción</option>
            @foreach($llegos as $opcion)
                <option value="{{ $opcion['id'] }}" @selected($selectedLlego === (string) $opcion['id'] || $selectedLlego === $opcion['label'])>{{ $opcion['label'] }}</option>
            @endforeach
        </select>
    </div>
</div>

<p class="mt-4 text-xs text-slate-500">Los campos deshabilitados no existen aún en la tabla <code>clientes_potenciales</code> de esta instancia y se muestran como fallback visual.</p>

@once
    @push('scripts')
        <script>
            (() => {
                const codigoInput = document.getElementById('codigo');
                const codigoEstado = document.getElementById('codigo_estado');
                const codigoModoEstado = document.getElementById('codigo_modo_estado');
                const codigoModeInputs = document.querySelectorAll('input[name="codigo_mode"]');

                if (codigoInput && codigoEstado && codigoModeInputs.length) {
                    const availabilityUrl = `{{ route('clientes.codigo.disponibilidad') }}`;
                    const nextCodigoUrl = `{{ route('clientes.codigo.siguiente') }}`;
                    let availabilityTimeout = null;
                    let nextTimeout = null;
                    let availabilityController = null;
                    let nextController = null;

                    const normalizeCodigo = (value) => value.trim().toUpperCase();

                    const selectedMode = () => {
                        const current = Array.from(codigoModeInputs).find((input) => input.checked);
                        return current ? current.value : 'manual';
                    };

                    const setCodigoEstado = (message, tone = 'neutral') => {
                        codigoEstado.textContent = message;
                        codigoEstado.classList.remove('text-slate-500', 'text-emerald-600', 'text-rose-600');

                        if (tone === 'success') {
                            codigoEstado.classList.add('text-emerald-600');
                            return;
                        }

                        if (tone === 'error') {
                            codigoEstado.classList.add('text-rose-600');
                            return;
                        }

                        codigoEstado.classList.add('text-slate-500');
                    };

                    const syncModoHint = () => {
                        if (!codigoModoEstado) {
                            return;
                        }

                        codigoModoEstado.textContent = selectedMode() === 'secuencia'
                            ? 'Usa el código actual como referencia y se completará el siguiente consecutivo.'
                            : 'Puedes escribir el código libremente. La disponibilidad se valida en tiempo real.';
                    };

                    const validateAvailability = async (value) => {
                        const codigo = normalizeCodigo(value);

                        if (availabilityController) {
                            availabilityController.abort();
                        }

                        if (codigo === '') {
                            setCodigoEstado('Escribe un código para validar.');
                            return;
                        }

                        availabilityController = new AbortController();
                        setCodigoEstado('Validando código...');

                        try {
                            const response = await fetch(`${availabilityUrl}?codigo=${encodeURIComponent(codigo)}`, {
                                headers: { 'Accept': 'application/json' },
                                signal: availabilityController.signal,
                            });
                            const payload = await response.json();

                            if (!response.ok) {
                                setCodigoEstado(payload.message ?? 'No fue posible validar el código.', 'error');
                                return;
                            }

                            setCodigoEstado(
                                payload.message ?? (payload.available ? 'Código disponible' : 'Código en uso'),
                                payload.available ? 'success' : 'error'
                            );
                        } catch (error) {
                            if (error.name === 'AbortError') {
                                return;
                            }

                            setCodigoEstado('No fue posible validar el código.', 'error');
                        }
                    };

                    const requestNextCodigo = async (value) => {
                        const hint = normalizeCodigo(value);

                        if (nextController) {
                            nextController.abort();
                        }

                        nextController = new AbortController();
                        setCodigoEstado('Buscando siguiente consecutivo...');

                        try {
                            const response = await fetch(`${nextCodigoUrl}?codigo=${encodeURIComponent(hint)}`, {
                                headers: { 'Accept': 'application/json' },
                                signal: nextController.signal,
                            });
                            const payload = await response.json();

                            if (!response.ok || !payload.codigo) {
                                setCodigoEstado(payload.message ?? 'No fue posible generar el siguiente código.', 'error');
                                return;
                            }

                            codigoInput.value = payload.codigo;
                            await validateAvailability(payload.codigo);
                        } catch (error) {
                            if (error.name === 'AbortError') {
                                return;
                            }

                            setCodigoEstado('No fue posible generar el siguiente código.', 'error');
                        }
                    };

                    const scheduleAvailabilityValidation = () => {
                        clearTimeout(availabilityTimeout);
                        availabilityTimeout = setTimeout(() => {
                            validateAvailability(codigoInput.value);
                        }, 300);
                    };

                    const scheduleNextCodigo = () => {
                        clearTimeout(nextTimeout);
                        nextTimeout = setTimeout(() => {
                            requestNextCodigo(codigoInput.value);
                        }, 350);
                    };

                    codigoInput.addEventListener('input', () => {
                        codigoInput.value = normalizeCodigo(codigoInput.value);

                        if (selectedMode() === 'secuencia') {
                            scheduleNextCodigo();
                            return;
                        }

                        scheduleAvailabilityValidation();
                    });

                    codigoInput.addEventListener('blur', () => {
                        if (selectedMode() === 'secuencia') {
                            requestNextCodigo(codigoInput.value);
                            return;
                        }

                        validateAvailability(codigoInput.value);
                    });

                    codigoModeInputs.forEach((input) => {
                        input.addEventListener('change', () => {
                            syncModoHint();

                            if (selectedMode() === 'secuencia') {
                                requestNextCodigo(codigoInput.value);
                                return;
                            }

                            validateAvailability(codigoInput.value);
                        });
                    });

                    syncModoHint();

                    if (selectedMode() === 'secuencia') {
                        requestNextCodigo(codigoInput.value);
                    } else {
                        validateAvailability(codigoInput.value);
                    }
                }

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
