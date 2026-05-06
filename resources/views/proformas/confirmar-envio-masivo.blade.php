@extends('layouts.admin')

@section('title', 'Confirmar envio masivo de proformas')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Confirmacion envio masivo grupo {{ $grupo }}</h1>
            <p class="mt-1 text-sm text-slate-600">Revisa el resumen antes de ejecutar el envio por correo.</p>
        </div>
        <a href="{{ route('proformas.index', ['mes' => $filtrosPeriodo['mes'] ?? null, 'anio' => $filtrosPeriodo['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver al listado</a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Total encontradas</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $resumen['total_encontradas'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Validas para enviar</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $resumen['validas_count'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Omitidas</p>
            <p class="mt-1 text-2xl font-semibold text-amber-600">{{ $resumen['omitidas_count'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Grupo fecha arriendo</p>
            <p class="mt-1 text-2xl font-semibold text-cyan-700">{{ $grupo }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-5 shadow">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Detalle de omitidas</h2>
        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div class="rounded bg-slate-50 px-3 py-2">Sin correo: <strong>{{ $resumen['omitidas_por_motivo']['sin_correo'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">Sin PDF: <strong>{{ $resumen['omitidas_por_motivo']['sin_pdf'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">Ya enviadas: <strong>{{ $resumen['omitidas_por_motivo']['ya_enviadas'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">No generadas: <strong>{{ $resumen['omitidas_por_motivo']['no_generadas'] }}</strong></div>
        </dl>
    </div>

    @if($resumen['validas_count'] > 0)
        <div class="rounded-lg bg-white p-5 shadow">
            <form method="POST" action="{{ route('proformas.envio-masivo.enviar', ['grupo' => $grupo]) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="mes" value="{{ $filtrosPeriodo['mes'] ?? '' }}">
                <input type="hidden" name="anio" value="{{ $filtrosPeriodo['anio'] ?? '' }}">

                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">Selecciona las proformas que se enviaran. Puedes desmarcar cualquiera antes de confirmar.</p>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="seleccionar-todas-proformas" checked class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                        Seleccionar todas
                    </label>
                </div>

                <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Enviar</th>
                            <th class="px-4 py-3">Proforma</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Fecha arriendo</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($resumen['validas'] as $proforma)
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="proformas[]" value="{{ $proforma->id }}" checked class="proforma-checkbox rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</p>
                                    <p class="text-xs text-slate-500">ID {{ $proforma->id }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $proforma->emp ?: 'N/D' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $proforma->cliente_email ?: 'Sin correo' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $proforma->cliente_fecha_arriendo ?: 'N/D' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-sm text-slate-600">Seleccionadas para enviar: <strong id="contador-proformas-seleccionadas">{{ $resumen['validas_count'] }}</strong></p>

                <button type="submit" class="inline-flex items-center rounded bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">Confirmar y enviar grupo {{ $grupo }}</button>
            </form>
        </div>
    @else
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            No hay proformas validas para envio en este grupo.
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('seleccionar-todas-proformas');
        const checkboxes = Array.from(document.querySelectorAll('.proforma-checkbox'));
        const counter = document.getElementById('contador-proformas-seleccionadas');

        if (!selectAll || checkboxes.length === 0 || !counter) {
            return;
        }

        const syncCounter = () => {
            const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
            counter.textContent = String(checkedCount);
            selectAll.checked = checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        };

        selectAll.addEventListener('change', function () {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });

            syncCounter();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncCounter);
        });

        syncCounter();
    });
</script>
@endpush
