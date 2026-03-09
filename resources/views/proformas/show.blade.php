@extends('layouts.admin')

@section('title', 'Detalle de Proforma')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-10">
    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @php($canSendProforma = $proformasService->canSendProforma($proforma))

    <div class="rounded-lg bg-white p-6 shadow">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Detalle de proforma #{{ $proforma->nro_prof ?: $proforma->id }}</h1>
                <p class="mt-1 text-sm text-slate-600">Gestión manual de estado de la proforma.</p>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}">{{ $proformasService->estadoLabel($proforma->estado) }}</span>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proformasService->envioBadgeClass($proforma->enviado ?? 0) }}">{{ $proformasService->envioLabel($proforma->enviado ?? 0) }}</span>
            </div>
        </div>

        <div class="mt-6 border-t border-slate-200 pt-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Cambiar estado</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_ENVIADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_ENVIADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Marcar enviada</button>
                    </form>
                @endif
                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_PAGADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-amber-100 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-200">Marcar pagada</button>
                    </form>
                @endif
                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_FACTURADA))
                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_FACTURADA }}">
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex items-center rounded bg-purple-100 px-4 py-2 text-sm font-medium text-purple-700 hover:bg-purple-200">Marcar facturada</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
