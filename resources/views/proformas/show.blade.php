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
        <a href="{{ route('proformas.index', ['from' => 'detalle']) }}">
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
                    <dt class="text-slate-500">Valor total</dt>
                    <dd class="font-medium text-slate-900">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Estado</dt>
                    <dd>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}">{{ $proformasService->estadoLabel($proforma->estado) }}</span>
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
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_PAGADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200">Marcar pagada</button>
                    </form>
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

        if (!form || !button || !feedback || !envioTexto || !fechaEnvioTexto || !intentosEnvioTexto || !envioBadge) {
            return;
        }

        const csrfToken = @json(csrf_token());
        const initialButtonText = button.textContent;

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

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

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
                    throw new Error(payload.message || 'No se pudo enviar el correo.');
                }

                envioTexto.textContent = 'Si';
                fechaEnvioTexto.textContent = formatFecha(payload.proforma?.fecha_envio || null);
                intentosEnvioTexto.textContent = String(payload.proforma?.intentos_envio ?? 0);
                envioBadge.textContent = 'Enviada';
                envioBadge.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${envioBadge.dataset.classEnviado}`;
                button.textContent = 'Reenviar por correo';

                showFeedback(payload.message || 'Proforma enviada por correo correctamente.', 'success');
            } catch (error) {
                button.textContent = initialButtonText;
                showFeedback(error.message || 'No se pudo enviar el correo.', 'error');
            } finally {
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    })();
</script>
@endpush
