<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detalle proforma #{{ $proforma->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="mx-auto max-w-4xl px-4 py-10">
    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Detalle de proforma #{{ $proforma->nro_prof ?: $proforma->id }}</h1>
                <p class="mt-1 text-sm text-slate-600">Gestión manual de estado de la proforma.</p>
            </div>
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proformasService->estadoBadgeClass($proforma->estado) }}">
                {{ $proformasService->estadoLabel($proforma->estado) }}
            </span>
        </div>

        <dl class="grid grid-cols-1 gap-4 rounded border border-slate-200 bg-slate-50 p-4 text-sm md:grid-cols-3">
            <div>
                <dt class="text-slate-500">Empresa</dt>
                <dd class="font-medium">{{ $proforma->emp ?: 'N/D' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">NIT</dt>
                <dd class="font-medium">{{ $proforma->nit ?: 'N/D' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Emisora</dt>
                <dd class="font-medium">{{ $proforma->emisora ?: 'N/D' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Mes/Año</dt>
                <dd class="font-medium">{{ $proformasService->monthLabel($proforma->mes) }} {{ $proforma->anio ?: 'N/D' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Valor total</dt>
                <dd class="font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">ID</dt>
                <dd class="font-medium">{{ $proforma->id }}</dd>
            </div>
        </dl>

        <div class="mt-6 border-t border-slate-200 pt-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Cambiar estado</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_PAGADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-amber-100 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-200">Marcar pagada</button>
                    </form>
                @endif

                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_FACTURADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_FACTURADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-purple-100 px-4 py-2 text-sm font-medium text-purple-700 hover:bg-purple-200">Marcar facturada</button>
                    </form>
                @endif

                @if(!$proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA) && !$proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_FACTURADA))
                    <p class="text-sm text-slate-500">No hay transiciones disponibles para este estado.</p>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Volver al listado</a>

            <a href="{{ route('proformas.dashboard') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">Ir al dashboard</a>
            <a href="{{ route('proformas.pdf.show', $proforma->id) }}" target="_blank" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">Ver PDF</a>
            <a href="{{ route('proformas.pdf.download', $proforma->id) }}" class="inline-flex items-center rounded bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-200">Descargar PDF</a>
            <form method="POST" action="{{ route('proformas.enviar', $proforma->id) }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200">Enviar por correo</button>
            </form>

        </div>
    </div>
</div>
</body>
</html>
