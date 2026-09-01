@extends('layouts.admin')

@section('title', 'Detalle de Proforma')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-10">
    @if(session('status'))
        <div id="proforma-envio-feedback" class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @else
        <div id="proforma-envio-feedback" class="mb-4 hidden rounded border px-4 py-3 text-sm"></div>
    @endif

    @php
        $canSendProforma = $proformasService->canSendProforma($proforma);
        $ultimoEnvio = $proforma->fecha_envio
            ? \Illuminate\Support\Carbon::parse($proforma->fecha_envio)->format('Y-m-d H:i')
            : 'N/D';
        $rutaPdf = trim((string) ($proforma->rpdf ?? ''));
        $nombrePdf = trim((string) ($proforma->npdf ?? ''));
        $hashPdf = trim((string) ($proforma->hpdf ?? ''));
    @endphp

    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Detalle de proforma #{{ $proforma->nro_prof ?: $proforma->id }}</h1>
            <p class="mt-1 text-sm text-slate-600">Vista consolidada de informacion operativa y tecnica.</p>
        </div>
        <a href="{{ route('proformas.back-to-index', $proforma->id) }}">
            Volver al listado
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="rounded-lg bg-white p-5 shadow lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Resumen</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Numero de proforma</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->nro_prof ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Empresa</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->emp ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">NIT</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->nit ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Emisora</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->emisora ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Mes</dt>
                    <dd class="font-medium text-slate-900">{{ $proformasService->monthLabel($proforma->mes) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Ano</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->anio ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Origen de resolucion</dt>
                    <dd>
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $proformasService->resolutionSourceBadgeClass($proforma->cliente_resolution_source ?? null) }}">
                            {{ $proformasService->resolutionSourceLabel($proforma->cliente_resolution_source ?? null) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Valor total</dt>
                    <dd class="font-medium text-slate-900">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Estado</dt>
                    <dd>
                        <span
                            id="proforma-estado-badge"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            data-label-generada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_GENERADA) }}"
                            data-label-enviada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_ENVIADA) }}"
                            data-label-pagada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                            data-label-facturada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                            data-style-generada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_GENERADA) }}"
                            data-style-enviada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_ENVIADA) }}"
                            data-style-pagada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                            data-style-facturada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                            style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}"
                        >{{ $proformasService->estadoLabel($proforma->estado) }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Estado de envio</dt>
                    <dd>
                        <span id="proforma-envio-badge" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proformasService->envioBadgeClass($proforma->enviado ?? 0) }}" data-class-enviado="{{ $proformasService->envioBadgeClass(1) }}" data-class-no-enviado="{{ $proformasService->envioBadgeClass(0) }}">{{ $proformasService->envioLabel($proforma->enviado ?? 0) }}</span>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Envio</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Enviado</dt>
                    <dd id="proforma-enviado-texto" class="font-medium text-slate-900">{{ ((int) ($proforma->enviado ?? 0)) === 1 ? 'Si' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Fecha ultimo envio</dt>
                    <dd id="proforma-fecha-envio-texto" class="font-medium text-slate-900">{{ $ultimoEnvio }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Intentos de envio</dt>
                    <dd id="proforma-intentos-envio-texto" class="font-medium text-slate-900">{{ (int) ($proforma->intentos_envio ?? 0) }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg bg-white p-5 shadow lg:col-span-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tecnica</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                <div>
                    <dt class="text-slate-500">ID interno</dt>
                    <dd class="font-medium text-slate-900">{{ $proforma->id }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-slate-500">Nombre / ruta PDF</dt>
                    <dd class="break-all font-medium text-slate-900">{{ $rutaPdf !== '' || $nombrePdf !== '' ? trim($rutaPdf.'/'.$nombrePdf, '/') : 'N/D' }}</dd>
                </div>
                <div class="md:col-span-3">
                    <dt class="text-slate-500">Hash PDF</dt>
                    <dd class="break-all font-medium text-slate-900">{{ $hashPdf !== '' ? $hashPdf : 'N/D' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg bg-white p-5 shadow lg:col-span-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Acciones</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('proformas.pdf.show', $proforma->id) }}" target="_blank" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Ver PDF</a>
                <a href="{{ route('proformas.pdf.download', $proforma->id) }}" class="inline-flex items-center rounded bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200">Descargar PDF</a>
                @if(trim((string) ($proforma->comprobante_pago ?? '')) !== '')
                    <a href="{{ route('proformas.comprobante-pago.show', $proforma->id) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-200">Ver comprobante</a>
                @endif

                @if($canSendProforma)
                    <form method="POST" action="{{ route('proformas.enviar', $proforma->id) }}" data-proforma-enviar-form>
                        @csrf
                        <button type="submit" data-proforma-enviar-button class="inline-flex items-center rounded bg-cyan-100 px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-200">{{ ((int) ($proforma->enviado ?? 0)) === 1 ? 'Reenviar' : 'Enviar' }} por correo</button>
                    </form>
                @else
                    <span class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500">Debe generar la proforma</span>
                @endif

                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_ENVIADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_ENVIADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Marcar enviada</button>
                    </form>
                @endif

                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA))
                    <button id="pago-modal-abrir" type="button" class="inline-flex items-center rounded bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200">Marcar pagada</button>
                @endif

                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_FACTURADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_FACTURADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-purple-100 px-3 py-1.5 text-xs font-medium text-purple-700 hover:bg-purple-200">Marcar facturada</button>
                    </form>
                @else
                    <span class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500">Facturada (pendiente en flujo)</span>
                @endif
            </div>
        </section>
    </div>

    @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA))
        <div id="pago-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="pago-modal-titulo">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 id="pago-modal-titulo" class="text-base font-semibold text-slate-900">Marcar proforma como pagada</h2>
                    <button id="pago-modal-cerrar-superior" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">X</button>
                </div>

                <form id="pago-form" method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}" enctype="multipart/form-data" class="space-y-4 px-5 py-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_PAGADA }}">
                    <input type="hidden" name="redirect_to" value="show">

                    <div>
                        <label for="pago-metodo" class="mb-1 block text-sm font-medium text-slate-700">Método de pago</label>
                        <select id="pago-metodo" name="fpago" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="">Seleccionar...</option>
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                            <option value="CONSIGNACIÓN">Consignación</option>
                        </select>
                    </div>

                    <div id="pago-comprobante-contenedor" class="hidden">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Comprobante de pago</span>
                        <input id="pago-comprobante" name="comprobante_pago" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only">
                        <div class="flex items-center gap-3">
                            <button id="pago-comprobante-abrir" type="button" class="rounded bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60" aria-controls="pago-comprobante">
                                <span id="pago-comprobante-boton-texto">Elegir archivo</span>
                            </button>
                            <span id="pago-comprobante-nombre" class="min-w-0 truncate text-sm text-slate-600" aria-live="polite">Ningún archivo seleccionado.</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG, WEBP o PDF. Máximo 10 MB.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <button id="pago-modal-cancelar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
                        <button id="pago-modal-confirmar" type="submit" class="rounded bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60">Confirmar pago</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-proforma-enviar-form]');
        const button = document.querySelector('[data-proforma-enviar-button]');
        const feedback = document.getElementById('proforma-envio-feedback');
        const envioTexto = document.getElementById('proforma-enviado-texto');
        const fechaEnvioTexto = document.getElementById('proforma-fecha-envio-texto');
        const intentosEnvioTexto = document.getElementById('proforma-intentos-envio-texto');
        const envioBadge = document.getElementById('proforma-envio-badge');
        const estadoBadge = document.getElementById('proforma-estado-badge');

        if (!form || !button || !feedback || !envioTexto || !fechaEnvioTexto || !intentosEnvioTexto || !envioBadge || !estadoBadge) {
            return;
        }

        const csrfToken = @json(csrf_token());
        const initialButtonText = button.textContent;
        const initialIsReenvio = initialButtonText.toLowerCase().includes('reenviar');
        const ESTADO_GENERADA = {{ \App\Services\ProformasService::ESTADO_GENERADA }};
        const ESTADO_ENVIADA = {{ \App\Services\ProformasService::ESTADO_ENVIADA }};
        const ESTADO_PAGADA = {{ \App\Services\ProformasService::ESTADO_PAGADA }};
        const ESTADO_FACTURADA = {{ \App\Services\ProformasService::ESTADO_FACTURADA }};

        const showFeedback = (message, type = 'success') => {
            feedback.textContent = message;
            feedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'border-rose-200', 'bg-rose-50', 'text-rose-700');
            feedback.classList.add(...(type === 'success'
                ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700']
                : ['border-rose-200', 'bg-rose-50', 'text-rose-700']));
        };

        const formatFecha = (value) => {
            if (!value) {
                return 'N/D';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            const hh = String(date.getHours()).padStart(2, '0');
            const mi = String(date.getMinutes()).padStart(2, '0');

            return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
        };

        const updateEstadoBadge = (estado) => {
            const map = {
                [ESTADO_GENERADA]: {
                    label: estadoBadge.dataset.labelGenerada,
                    style: estadoBadge.dataset.styleGenerada,
                },
                [ESTADO_ENVIADA]: {
                    label: estadoBadge.dataset.labelEnviada,
                    style: estadoBadge.dataset.styleEnviada,
                },
                [ESTADO_PAGADA]: {
                    label: estadoBadge.dataset.labelPagada,
                    style: estadoBadge.dataset.stylePagada,
                },
                [ESTADO_FACTURADA]: {
                    label: estadoBadge.dataset.labelFacturada,
                    style: estadoBadge.dataset.styleFacturada,
                },
            };

            const estadoInfo = map[estado];
            if (!estadoInfo) {
                return;
            }

            estadoBadge.textContent = estadoInfo.label;
            estadoBadge.setAttribute('style', estadoInfo.style);
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const isReenvio = button.textContent.toLowerCase().includes('reenviar') || initialIsReenvio;
            const successMessage = isReenvio
                ? 'Proforma reenviada por correo correctamente.'
                : 'Proforma enviada por correo correctamente.';
            const errorMessage = isReenvio
                ? 'No se pudo reenviar el correo.'
                : 'No se pudo enviar el correo.';

            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.textContent = 'Enviando...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || errorMessage);
                }

                envioTexto.textContent = 'Si';
                fechaEnvioTexto.textContent = formatFecha(payload.proforma?.fecha_envio || null);
                intentosEnvioTexto.textContent = String(payload.proforma?.intentos_envio ?? 0);
                envioBadge.textContent = 'Enviada';
                envioBadge.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${envioBadge.dataset.classEnviado}`;
                button.textContent = 'Reenviar por correo';

                if (payload.proforma?.estado !== undefined) {
                    updateEstadoBadge(Number(payload.proforma.estado));
                }

                showFeedback(payload.message || successMessage, 'success');
                feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (error) {
                button.textContent = initialButtonText;
                showFeedback(error.message || errorMessage, 'error');
                feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } finally {
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    })();
</script>
<script>
    (() => {
        const openButton = document.getElementById('pago-modal-abrir');
        const modal = document.getElementById('pago-modal');
        const form = document.getElementById('pago-form');
        const method = document.getElementById('pago-metodo');
        const receiptContainer = document.getElementById('pago-comprobante-contenedor');
        const receipt = document.getElementById('pago-comprobante');
        const receiptOpenButton = document.getElementById('pago-comprobante-abrir');
        const receiptButtonText = document.getElementById('pago-comprobante-boton-texto');
        const receiptName = document.getElementById('pago-comprobante-nombre');
        const closeTopButton = document.getElementById('pago-modal-cerrar-superior');
        const cancelButton = document.getElementById('pago-modal-cancelar');
        const confirmButton = document.getElementById('pago-modal-confirmar');

        if (!openButton || !modal || !form || !method || !confirmButton) {
            return;
        }

        let submitting = false;
        let receiptPickerOpening = false;
        let receiptFocusHandler = null;
        let receiptSafetyTimeout = null;

        const updateReceiptName = () => {
            if (receiptName) {
                receiptName.textContent = receipt?.files?.[0]?.name || 'Ningún archivo seleccionado.';
            }
        };

        const finishOpeningReceipt = () => {
            if (receiptFocusHandler) {
                window.removeEventListener('focus', receiptFocusHandler);
                receiptFocusHandler = null;
            }

            if (receiptSafetyTimeout) {
                window.clearTimeout(receiptSafetyTimeout);
                receiptSafetyTimeout = null;
            }

            receiptPickerOpening = false;
            if (receiptOpenButton) {
                receiptOpenButton.disabled = false;
            }
            if (receiptButtonText) {
                receiptButtonText.textContent = 'Elegir archivo';
            }
            updateReceiptName();
        };

        const openReceiptPicker = () => {
            if (!receipt || !receiptOpenButton || receiptPickerOpening) {
                return;
            }

            receiptPickerOpening = true;
            receiptOpenButton.disabled = true;
            if (receiptButtonText) {
                receiptButtonText.textContent = '⏳ Abriendo...';
            }

            receiptFocusHandler = () => window.setTimeout(finishOpeningReceipt, 0);
            window.addEventListener('focus', receiptFocusHandler, { once: true });
            receiptSafetyTimeout = window.setTimeout(finishOpeningReceipt, 30000);

            window.requestAnimationFrame(() => {
                window.setTimeout(() => {
                    if (receiptPickerOpening) {
                        receipt.click();
                    }
                }, 0);
            });
        };

        const syncReceiptRequirement = () => {
            if (!receiptContainer || !receipt) {
                return;
            }

            const required = method.value === 'TRANSFERENCIA' || method.value === 'CONSIGNACIÓN';
            receipt.required = required;
            receiptContainer.classList.toggle('hidden', !required);

            if (!required) {
                receipt.value = '';
                updateReceiptName();
            }
        };

        const openModal = () => {
            submitting = false;
            form.reset();
            finishOpeningReceipt();
            syncReceiptRequirement();
            confirmButton.disabled = false;
            confirmButton.textContent = 'Confirmar pago';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            method.focus();
        };

        const closeModal = () => {
            if (submitting) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            form.reset();
            finishOpeningReceipt();
        };

        openButton.addEventListener('click', openModal);
        method.addEventListener('change', syncReceiptRequirement);
        receiptOpenButton?.addEventListener('click', openReceiptPicker);
        receipt?.addEventListener('change', finishOpeningReceipt);
        receipt?.addEventListener('cancel', finishOpeningReceipt);
        [closeTopButton, cancelButton].forEach((button) => {
            button?.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('flex')) {
                closeModal();
            }
        });

        form.addEventListener('submit', (event) => {
            if (submitting) {
                event.preventDefault();
                return;
            }

            submitting = true;
            confirmButton.disabled = true;
            confirmButton.textContent = 'Confirmando...';
        });
    })();
</script>
@endpush
