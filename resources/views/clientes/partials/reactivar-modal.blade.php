@php
    $motivosReactivacion = $motivosReactivacion ?? ['options' => []];
    $modalClienteId = old('cliente_reactivacion_id');
    $modalAction = $modalClienteId ? route('clientes.reactivar', $modalClienteId) : route('clientes.reactivar', 0);
    $shouldOpenModal = $errors->has('motivo_reactivacion') || $errors->has('observacion_reactivacion');
@endphp

<div
    id="reactivar-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 px-4 py-6"
    aria-hidden="true"
>
    <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Reactivar cliente</h2>
                <p class="mt-1 text-sm text-slate-500" id="reactivar-modal-subtitle">Selecciona el motivo y confirma la reactivación.</p>
            </div>
            <button type="button" class="rounded p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-reactivar-close aria-label="Cerrar modal">
                X
            </button>
        </div>

        <form id="reactivar-modal-form" method="POST" action="{{ $modalAction }}" class="px-5 py-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="cliente_reactivacion_id" id="cliente_reactivacion_id" value="{{ $modalClienteId }}">

            <div class="space-y-4">
                <div>
                    <label for="motivo_reactivacion" class="mb-1 block text-sm font-medium text-slate-700">Motivo reactivación</label>
                    <select
                        id="motivo_reactivacion"
                        name="motivo_reactivacion"
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        @disabled(($motivosReactivacion['options'] ?? []) === [])
                    >
                        <option value="">Selecciona un motivo</option>
                        @foreach(($motivosReactivacion['options'] ?? []) as $motivo)
                            <option value="{{ $motivo['id'] }}" @selected((string) old('motivo_reactivacion') === (string) $motivo['id'])>{{ $motivo['label'] }}</option>
                        @endforeach
                    </select>
                    @error('motivo_reactivacion')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    @if(($motivosReactivacion['options'] ?? []) === [])
                        <p class="mt-1 text-xs text-amber-600">No hay motivos activos disponibles en la tabla <code>motivos_re</code>.</p>
                    @endif
                </div>

                <div>
                    <label for="observacion_reactivacion" class="mb-1 block text-sm font-medium text-slate-700">Observación</label>
                    <textarea
                        id="observacion_reactivacion"
                        name="observacion_reactivacion"
                        rows="4"
                        placeholder="Opcional"
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    >{{ old('observacion_reactivacion') }}</textarea>
                    @error('observacion_reactivacion')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                <button type="button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-reactivar-close>
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
                    @disabled(($motivosReactivacion['options'] ?? []) === [])
                >
                    Reactivar cliente
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const modal = document.getElementById('reactivar-modal');
                const form = document.getElementById('reactivar-modal-form');
                const hiddenId = document.getElementById('cliente_reactivacion_id');
                const subtitle = document.getElementById('reactivar-modal-subtitle');

                if (!modal || !form || !hiddenId || !subtitle) {
                    return;
                }

                const openModal = ({ action, clienteId, clienteNombre }) => {
                    form.action = action;
                    hiddenId.value = clienteId ?? '';
                    subtitle.textContent = clienteNombre
                        ? `Selecciona el motivo y confirma la reactivación de ${clienteNombre}.`
                        : 'Selecciona el motivo y confirma la reactivación.';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                };

                document.querySelectorAll('[data-reactivar-url]').forEach((trigger) => {
                    trigger.addEventListener('click', () => {
                        openModal({
                            action: trigger.dataset.reactivarUrl,
                            clienteId: trigger.dataset.reactivarId,
                            clienteNombre: trigger.dataset.reactivarNombre,
                        });
                    });
                });

                document.querySelectorAll('[data-reactivar-close]').forEach((trigger) => {
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
                    const originalTrigger = document.querySelector(`[data-reactivar-id="{{ $modalClienteId }}"]`);
                    openModal({
                        action: originalTrigger?.dataset.reactivarUrl ?? @js($modalAction),
                        clienteId: @js($modalClienteId),
                        clienteNombre: originalTrigger?.dataset.reactivarNombre ?? '',
                    });
                @endif
            })();
        </script>
    @endpush
@endonce
