@php
    $motivosRetiro = $motivosRetiro ?? ['options' => []];
    $modalClienteId = old('cliente_retiro_id');
    $modalAction = $modalClienteId ? route('clientes.retirar', $modalClienteId) : route('clientes.retirar', 0);
    $defaultRetiroDate = now()->toDateString();
    $shouldOpenModal = $errors->has('motivo_retiro') || $errors->has('fecha_retiro');
@endphp

<div
    id="retirar-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 px-4 py-6"
    aria-hidden="true"
>
    <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Retirar cliente</h2>
                <p class="mt-1 text-sm text-slate-500" id="retirar-modal-subtitle">Selecciona el motivo y confirma el retiro.</p>
            </div>
            <button type="button" class="rounded p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-retirar-close aria-label="Cerrar modal">
                X
            </button>
        </div>

        <form id="retirar-modal-form" method="POST" action="{{ $modalAction }}" class="px-5 py-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="cliente_retiro_id" id="cliente_retiro_id" value="{{ $modalClienteId }}">

            <div class="space-y-4">
                <div>
                    <label for="motivo_retiro" class="mb-1 block text-sm font-medium text-slate-700">Motivo retiro</label>
                    <select
                        id="motivo_retiro"
                        name="motivo_retiro"
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                        @disabled(($motivosRetiro['options'] ?? []) === [])
                    >
                        <option value="">Selecciona un motivo</option>
                        @foreach(($motivosRetiro['options'] ?? []) as $motivo)
                            <option value="{{ $motivo['id'] }}" @selected((string) old('motivo_retiro') === (string) $motivo['id'])>{{ $motivo['label'] }}</option>
                        @endforeach
                    </select>
                    @error('motivo_retiro')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    @if(($motivosRetiro['options'] ?? []) === [])
                        <p class="mt-1 text-xs text-amber-600">No hay motivos disponibles en la tabla <code>conceptos_r</code>.</p>
                    @endif
                </div>

                <div>
                    <label for="fecha_retiro" class="mb-1 block text-sm font-medium text-slate-700">Fecha de retiro</label>
                    <input
                        type="date"
                        id="fecha_retiro"
                        name="fecha_retiro"
                        value="{{ old('fecha_retiro', $defaultRetiroDate) }}"
                        required
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                    >
                    @error('fecha_retiro')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                <button type="button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-retirar-close>
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="inline-flex items-center rounded bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300"
                    @disabled(($motivosRetiro['options'] ?? []) === [])
                >
                    Confirmar retiro
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const modal = document.getElementById('retirar-modal');
                const form = document.getElementById('retirar-modal-form');
                const hiddenId = document.getElementById('cliente_retiro_id');
                const subtitle = document.getElementById('retirar-modal-subtitle');
                const retiroDateInput = document.getElementById('fecha_retiro');
                const defaultRetiroDate = @js($defaultRetiroDate);
                const hasOldRetiroDate = @js(old('fecha_retiro') !== null);

                if (!modal || !form || !hiddenId || !subtitle || !retiroDateInput) {
                    return;
                }

                const openModal = ({ action, clienteId, clienteNombre }) => {
                    form.action = action;
                    hiddenId.value = clienteId ?? '';
                    if (!hasOldRetiroDate) {
                        retiroDateInput.value = defaultRetiroDate;
                    }
                    subtitle.textContent = clienteNombre
                        ? `Selecciona el motivo y confirma el retiro de ${clienteNombre}.`
                        : 'Selecciona el motivo y confirma el retiro.';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                };

                document.querySelectorAll('[data-retirar-url]').forEach((trigger) => {
                    trigger.addEventListener('click', () => {
                        openModal({
                            action: trigger.dataset.retirarUrl,
                            clienteId: trigger.dataset.retirarId,
                            clienteNombre: trigger.dataset.retirarNombre,
                        });
                    });
                });

                document.querySelectorAll('[data-retirar-close]').forEach((trigger) => {
                    trigger.addEventListener('click', closeModal);
                });

                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                window.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });

                @if($shouldOpenModal && $modalClienteId)
                    const originalTrigger = document.querySelector(`[data-retirar-id="{{ $modalClienteId }}"]`);
                    openModal({
                        action: originalTrigger?.dataset.retirarUrl ?? @js($modalAction),
                        clienteId: @js($modalClienteId),
                        clienteNombre: originalTrigger?.dataset.retirarNombre ?? '',
                    });
                @endif
            })();
        </script>
    @endpush
@endonce
