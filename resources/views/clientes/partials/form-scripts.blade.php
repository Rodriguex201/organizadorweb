@once
    @push('scripts')
        <script>
            (() => {
                const toUppercase = (value) => String(value ?? '').toLocaleUpperCase('es-CO');
                const clienteForm = document.querySelector('form[action*="/clientes"]');
                const isEditForm = clienteForm?.querySelector('input[name="_method"][value="PUT"]') !== null;

                if (clienteForm) {
                    const uppercaseSelector = 'input[type="text"]:not([type="hidden"]):not([name="email"]), textarea';

                    clienteForm.querySelectorAll(uppercaseSelector).forEach((field) => {
                        field.addEventListener('input', () => {
                            const uppercased = toUppercase(field.value);

                            if (field.value !== uppercased) {
                                field.value = uppercased;
                            }
                        });
                    });
                }

                const codigoInput = document.getElementById('codigo');
                const codigoEstado = document.getElementById('codigo_estado');
                const codigoModoEstado = document.getElementById('codigo_modo_estado');
                const codigoModeInputs = document.querySelectorAll('input[name="codigo_mode"]');

                if (codigoInput && codigoEstado) {
                    const availabilityUrl = `{{ route('clientes.codigo.disponibilidad') }}`;
                    const nextCodigoUrl = `{{ route('clientes.codigo.siguiente') }}`;
                    const currentClienteId = clienteForm?.dataset.clienteId ?? '';
                    let availabilityTimeout = null;
                    let nextTimeout = null;
                    let availabilityController = null;
                    let nextController = null;
                    let codigoDisponible = !isEditForm;

                    const normalizeCodigo = (value) => toUppercase(value).trim();

                    const selectedMode = () => {
                        const current = Array.from(codigoModeInputs).find((input) => input.checked);
                        return current ? current.value : 'manual';
                    };

                    const availabilityParams = (codigo) => {
                        const params = new URLSearchParams({ codigo });

                        if (currentClienteId !== '') {
                            params.set('exclude_id', currentClienteId);
                        }

                        return params.toString();
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
                            codigoDisponible = !isEditForm;
                            setCodigoEstado('Escribe un código para validar.');
                            return false;
                        }

                        availabilityController = new AbortController();
                        setCodigoEstado('Validando código...');

                        try {
                            const response = await fetch(`${availabilityUrl}?${availabilityParams(codigo)}`, {
                                headers: { 'Accept': 'application/json' },
                                signal: availabilityController.signal,
                            });
                            const payload = await response.json();

                            if (!response.ok) {
                                codigoDisponible = false;
                                setCodigoEstado(payload.message ?? 'No fue posible validar el código.', 'error');
                                return false;
                            }

                            codigoDisponible = Boolean(payload.available);
                            setCodigoEstado(
                                payload.message ?? (payload.available ? 'Código disponible' : 'Código en uso'),
                                payload.available ? 'success' : 'error'
                            );
                            return payload.available;
                        } catch (error) {
                            if (error.name === 'AbortError') {
                                return null;
                            }

                            codigoDisponible = false;
                            setCodigoEstado('No fue posible validar el código.', 'error');
                            return false;
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
                        codigoDisponible = !isEditForm;

                        if (codigoModeInputs.length && selectedMode() === 'secuencia') {
                            scheduleNextCodigo();
                            return;
                        }

                        scheduleAvailabilityValidation();
                    });

                    codigoInput.addEventListener('blur', () => {
                        if (codigoModeInputs.length && selectedMode() === 'secuencia') {
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

                    if (codigoModeInputs.length) {
                        syncModoHint();

                        if (selectedMode() === 'secuencia') {
                            requestNextCodigo(codigoInput.value);
                        } else {
                            validateAvailability(codigoInput.value);
                        }
                    } else {
                        validateAvailability(codigoInput.value);
                    }

                    clienteForm?.addEventListener('submit', async (event) => {
                        if (!isEditForm || codigoInput.disabled) {
                            return;
                        }

                        const isAvailable = await validateAvailability(codigoInput.value);

                        if (isAvailable === false || codigoDisponible === false) {
                            event.preventDefault();
                            codigoInput.focus();
                        }
                    });
                }

                const inputBusqueda = document.getElementById('ciudad_busqueda');
                const botonBuscar = document.getElementById('ciudad_buscar_btn');
                const inputDepartamento = document.getElementById('departamento');
                const inputCiudadCodigo = document.getElementById('ciudad_codigo');
                const estado = document.getElementById('ciudad_estado');
                const resultados = document.getElementById('ciudad_resultados');

                if (inputBusqueda && botonBuscar && inputDepartamento && inputCiudadCodigo && estado && resultados) {
                    const setEstado = (texto, error = false) => {
                        estado.textContent = texto;
                        estado.classList.toggle('text-rose-600', error);
                        estado.classList.toggle('text-slate-500', !error);
                    };

                    const syncCityValidity = () => {
                        const hasSearchText = inputBusqueda.value.trim() !== '';
                        const hasSelectedCity = inputCiudadCodigo.value.trim() !== '';

                        if (!hasSearchText) {
                            inputBusqueda.setCustomValidity('Selecciona una ciudad del catalogo.');
                            return;
                        }

                        if (!hasSelectedCity) {
                            inputBusqueda.setCustomValidity('Debes buscar y seleccionar una ciudad valida de la lista.');
                            return;
                        }

                        inputBusqueda.setCustomValidity('');
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
                                inputBusqueda.value = toUppercase(item.label);
                                inputDepartamento.value = toUppercase(item.label);
                                inputCiudadCodigo.value = String(item.code ?? '').trim();
                                setEstado('Ciudad seleccionada.');
                                syncCityValidity();
                                limpiarResultados();
                            });

                            resultados.appendChild(opcion);
                        });
                    };

                    inputBusqueda.addEventListener('input', () => {
                        inputBusqueda.value = toUppercase(inputBusqueda.value);
                        inputDepartamento.value = '';
                        inputCiudadCodigo.value = '';
                        limpiarResultados();
                        setEstado('Usa buscar para seleccionar una ciudad.');
                        syncCityValidity();
                    });

                    botonBuscar.addEventListener('click', async () => {
                        const termino = inputBusqueda.value.trim();
                        inputDepartamento.value = '';
                        inputCiudadCodigo.value = '';
                        syncCityValidity();

                        if (termino.length < 3) {
                            limpiarResultados();
                            setEstado('Escribe al menos 3 caracteres para buscar.', true);
                            return;
                        }

                        setEstado('Buscando ciudades...');
                        limpiarResultados();

                        try {
                            const response = await fetch(`{{ route('ciudades.buscar') }}?q=${encodeURIComponent(termino)}`, {
                                headers: { 'Accept': 'application/json' },
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

                    syncCityValidity();
                }

                const proformaInputs = Array.from(document.querySelectorAll('[data-proforma-input]'));
                const valorTotalDisplay = document.getElementById('valor_total_display');
                const valorTotalInput = document.getElementById('valor_total');
                const restaurarTarifasButton = document.getElementById('restaurar_tarifas_button');

                if (proformaInputs.length && valorTotalDisplay && valorTotalInput) {
                    const toNumber = (value) => {
                        if (value === null || value === undefined || value === '') {
                            return 0;
                        }

                        const normalized = String(value).replace(',', '.');
                        const parsed = Number.parseFloat(normalized);

                        return Number.isFinite(parsed) ? parsed : 0;
                    };

                    const getValue = (id) => toNumber(document.getElementById(id)?.value ?? 0);
                    const currency = new Intl.NumberFormat('es-CO', {
                        style: 'currency',
                        currency: 'COP',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    });

                    const calculateTotal = () => {
                        const valorPrincipal = getValue('vlrprincipal');
                        const numeroEquipos = getValue('numequipos');
                        const valorTerminal = getValue('vlrterminal');
                        const numeroEquiposExtra = getValue('numextra');
                        const valorEquipoExtra = getValue('vlrextrae');
                        const valorExtra = getValue('vlrextra');
                        const valorExtra2 = getValue('vlrextra2');
                        const valorNomina = getValue('vlrnomina');
                        const numeroMoviles = getValue('numeromoviles');
                        const valorMovil = getValue('vlrmovil');

                        const equiposAdicionales = Math.max(numeroEquipos - 1, 0);

                        return valorPrincipal
                            + (valorTerminal * equiposAdicionales)
                            + (valorEquipoExtra * numeroEquiposExtra)
                            + valorExtra
                            + valorExtra2
                            + valorNomina
                            + (valorMovil * numeroMoviles);
                    };

                    const syncTotal = () => {
                        const total = calculateTotal();
                        valorTotalInput.value = total.toFixed(2);
                        valorTotalDisplay.textContent = currency.format(total);
                    };

                    proformaInputs.forEach((input) => {
                        input.addEventListener('input', syncTotal);
                        input.addEventListener('change', syncTotal);
                    });

                    restaurarTarifasButton?.addEventListener('click', () => {
                        proformaInputs.forEach((input) => {
                            if (input.disabled) {
                                return;
                            }

                            input.value = input.dataset.defaultValue ?? '';
                        });

                        syncTotal();
                    });

                    syncTotal();
                }
            })();
        </script>
    @endpush
@endonce
