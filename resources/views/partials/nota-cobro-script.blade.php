@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const notaButtons = document.querySelectorAll('.nota-cobro-btn');
        const notaModal = document.getElementById('nota-cobro-modal');
        const notaCliente = document.getElementById('nota-cobro-cliente');
        const notaTextarea = document.getElementById('nota-cobro-textarea');
        const notaFeedback = document.getElementById('nota-cobro-feedback');
        const notaGuardar = document.getElementById('nota-cobro-guardar');
        const notaLimpiar = document.getElementById('nota-cobro-limpiar');
        const notaCancelar = document.getElementById('nota-cobro-cancelar');
        const notaCancelarTop = document.getElementById('nota-cobro-cancelar-top');

        if (notaButtons.length === 0 || !notaModal || !notaTextarea || !notaCliente || !notaGuardar || !notaLimpiar || !notaCancelar || !notaCancelarTop || !notaFeedback) {
            return;
        }

        const csrfToken = @json(csrf_token());
        const updateUrlTemplate = @json(route('cobros.nota.update', ['id' => '__CLIENTE_ID__']));
        const clearUrlTemplate = @json(route('cobros.nota.clear', ['id' => '__CLIENTE_ID__']));

        let selectedClientId = null;
        let selectedButton = null;

        const closeNotaModal = () => {
            notaModal.classList.add('hidden');
            notaModal.classList.remove('flex');
            selectedClientId = null;
            selectedButton = null;
            notaFeedback.classList.add('hidden');
            notaFeedback.textContent = '';
            notaFeedback.classList.remove('text-rose-600', 'text-emerald-600');
        };

        const openNotaModal = (button) => {
            selectedButton = button;
            selectedClientId = button.dataset.clienteId;
            notaCliente.textContent = `Cliente: ${button.dataset.clienteNombre || 'Sin nombre'}`;
            notaTextarea.value = button.dataset.nota || '';
            notaFeedback.classList.add('hidden');
            notaFeedback.textContent = '';
            notaFeedback.classList.remove('text-rose-600', 'text-emerald-600');
            notaModal.classList.remove('hidden');
            notaModal.classList.add('flex');
            notaTextarea.focus();
        };

        const resumenNota = (nota) => {
            const notaNormalizada = (nota || '').trim();
            if (!notaNormalizada) {
                return 'Sin nota de cobro';
            }

            return notaNormalizada.length > 50 ? `${notaNormalizada.substring(0, 50)}…` : notaNormalizada;
        };

        const updateVisualState = (nota) => {
            if (!selectedButton) {
                return;
            }

            const hasNota = (nota || '').trim().length > 0;
            selectedButton.dataset.nota = nota || '';
            selectedButton.title = resumenNota(nota || '');
            selectedButton.classList.toggle('text-amber-600', hasNota);
            selectedButton.classList.toggle('text-slate-400', !hasNota);
        };

        const showFeedback = (message, isError = false) => {
            notaFeedback.textContent = message;
            notaFeedback.classList.remove('hidden', 'text-rose-600', 'text-emerald-600');
            notaFeedback.classList.add(isError ? 'text-rose-600' : 'text-emerald-600');
        };

        const setButtonsDisabled = (disabled) => {
            [notaGuardar, notaLimpiar, notaCancelar, notaCancelarTop].forEach((element) => {
                element.disabled = disabled;
            });
        };

        const requestNota = async (url, method, nota = null) => {
            const body = method === 'PATCH' ? JSON.stringify({ nota_cobro: nota }) : null;

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body,
            });

            const payload = await response.json();

            if (!response.ok) {
                const errorMessage = payload?.message || 'No fue posible actualizar la nota de cobro.';
                throw new Error(errorMessage);
            }

            return payload;
        };

        notaButtons.forEach((button) => {
            button.addEventListener('click', () => openNotaModal(button));
        });

        notaGuardar.addEventListener('click', async () => {
            if (!selectedClientId) {
                return;
            }

            setButtonsDisabled(true);

            try {
                const payload = await requestNota(updateUrlTemplate.replace('__CLIENTE_ID__', selectedClientId), 'PATCH', notaTextarea.value);
                updateVisualState(payload.nota_cobro || '');
                showFeedback(payload.message || 'Nota guardada correctamente.');
            } catch (error) {
                showFeedback(error.message || 'Error al guardar la nota.', true);
            } finally {
                setButtonsDisabled(false);
            }
        });

        notaLimpiar.addEventListener('click', async () => {
            if (!selectedClientId) {
                return;
            }

            setButtonsDisabled(true);

            try {
                const payload = await requestNota(clearUrlTemplate.replace('__CLIENTE_ID__', selectedClientId), 'DELETE');
                notaTextarea.value = '';
                updateVisualState('');
                showFeedback(payload.message || 'Nota eliminada correctamente.');
            } catch (error) {
                showFeedback(error.message || 'Error al limpiar la nota.', true);
            } finally {
                setButtonsDisabled(false);
            }
        });

        [notaCancelar, notaCancelarTop].forEach((button) => {
            button.addEventListener('click', closeNotaModal);
        });

        notaModal.addEventListener('click', (event) => {
            if (event.target === notaModal) {
                closeNotaModal();
            }
        });
    });
</script>
@endpush
