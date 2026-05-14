@extends('layouts.admin')

@section('title', 'Configuracion de tarifas')

@php
    $statusType = session('status_type', 'success');
    $alertClasses = match ($statusType) {
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'error' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };

    $categoryConfig = [
        'principal' => [
            'title' => 'Principal',
            'description' => 'Base principal para el cobro mensual.',
            'accent' => 'from-sky-500 to-cyan-500',
            'fields' => ['vlrprincipal', 'numequipos', 'vlrterminal'],
        ],
        'equipos_extra' => [
            'title' => 'Equipos extra',
            'description' => 'Valores adicionales para extras y equipos complementarios.',
            'accent' => 'from-amber-500 to-orange-500',
            'fields' => ['numextra', 'vlrextrae', 'vlrextra'],
        ],
        'nomina' => [
            'title' => 'Nomina',
            'description' => 'Tarifas asociadas al modulo de nomina.',
            'accent' => 'from-violet-500 to-indigo-500',
            'fields' => ['vlrnomina', 'nominaterminal', 'vlrterminal_nomina'],
        ],
        'facturacion' => [
            'title' => 'Facturacion',
            'description' => 'Conceptos usados para factura, soporte y recepcion.',
            'accent' => 'from-emerald-500 to-teal-500',
            'fields' => ['vlrfactura', 'vlrsoporte', 'vlrrecepcion'],
            'formula' => 'Suma de factura, soporte y recepcion.',
        ],
        'moviles' => [
            'title' => 'Moviles',
            'description' => 'Configuracion para cantidad y valor de moviles.',
            'accent' => 'from-fuchsia-500 to-pink-500',
            'fields' => ['numeromoviles', 'vlrmovil'],
            'formula' => 'Valor movil x numero moviles.',
        ],
        'otros' => [
            'title' => 'Otros',
            'description' => 'Campos adicionales disponibles en la configuracion global.',
            'accent' => 'from-slate-500 to-slate-700',
            'fields' => ['vlrterminal_recepcion'],
            'formula' => 'Suma simple de campos adicionales.',
        ],
    ];

    $categoryConfig['principal']['formula'] = 'Valor principal + (valor terminal x equipos adicionales).';
    $categoryConfig['equipos_extra']['formula'] = '(Valor equipo extra x numero extra) + otro valor extra.';
    $categoryConfig['nomina']['formula'] = 'Valor nomina + (valor terminal nomina x equipos nomina adicionales).';

    $friendlyLabels = [
        'vlrprincipal' => 'Valor principal',
        'numequipos' => 'Numero equipos',
        'vlrterminal' => 'Valor terminal',
        'numextra' => 'Numero equipos extra',
        'vlrextrae' => 'Valor equipo extra',
        'vlrextra' => 'Otro valor extra',
        'vlrnomina' => 'Valor nomina',
        'nominaterminal' => 'Numero equipos nomina',
        'vlrterminal_nomina' => 'Valor terminal nomina',
        'vlrfactura' => 'Valor factura',
        'vlrsoporte' => 'Valor soporte',
        'vlrrecepcion' => 'Valor recepcion',
        'numeromoviles' => 'Numero moviles',
        'vlrmovil' => 'Valor movil',
        'vlrterminal_recepcion' => 'Terminal recepcion',
    ];

    $tarifasByKey = $tarifas->keyBy('clave');
    $assignedKeys = collect($categoryConfig)
        ->flatMap(fn (array $config) => $config['fields'])
        ->values()
        ->all();

    $unassignedTarifas = $tarifas
        ->reject(fn ($tarifa) => in_array($tarifa->clave, $assignedKeys, true))
        ->values();

    if ($unassignedTarifas->isNotEmpty()) {
        $categoryConfig['otros']['fields'] = array_merge(
            $categoryConfig['otros']['fields'],
            $unassignedTarifas->pluck('clave')->all(),
        );
    }
@endphp

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Configuracion de tarifas</h1>
                <p class="text-sm text-slate-600">Edita los valores globales que se precargan en el paso 2 de <code>/clientes/create</code>. La vista ahora esta agrupada por bloques para ubicar tarifas mas rapido.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Registros</p>
                    <p class="text-lg font-bold text-slate-800">{{ $tarifas->count() }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Activas</p>
                    <p class="text-lg font-bold text-emerald-700">{{ $tarifas->where('activo', 1)->count() }}</p>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded border px-4 py-3 text-sm {{ $alertClasses }}">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('configuracion.tarifas.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Panel administrativo</h2>
                        <p class="text-sm text-slate-500">Cada bloque agrupa tarifas relacionadas para reducir altura y acelerar la edicion.</p>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Guardar tarifas
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                @foreach($categoryConfig as $categoryKey => $category)
                    @php
                        $categoryTarifas = collect($category['fields'])
                            ->map(fn (string $clave) => $tarifasByKey->get($clave))
                            ->filter()
                            ->values();
                    @endphp

                    @if($categoryTarifas->isEmpty())
                        @continue
                    @endif

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-tarifa-category="{{ $categoryKey }}">
                        <div class="bg-gradient-to-r {{ $category['accent'] }} px-4 py-3 text-white">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em]">{{ $category['title'] }}</h3>
                            <p class="mt-1 text-sm text-white/90">{{ $category['description'] }}</p>
                        </div>

                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            @foreach($categoryTarifas as $tarifa)
                                @php
                                    $baseKey = 'tarifas.'.$tarifa->clave;
                                    $label = $friendlyLabels[$tarifa->clave] ?? Str::of($tarifa->clave)->replace('_', ' ')->title();
                                @endphp
                                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3">
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div>
                                            <label for="tarifa_{{ $tarifa->clave }}" class="block text-sm font-semibold text-slate-800">{{ $label }}</label>
                                            <p class="mt-1 text-xs text-slate-500">{{ $tarifa->descripcion ?: 'Sin descripcion adicional.' }}</p>
                                        </div>
                                        <code class="rounded bg-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600">{{ $tarifa->clave }}</code>
                                    </div>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <label for="tarifa_{{ $tarifa->clave }}" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Valor</label>
                                            <input
                                                id="tarifa_{{ $tarifa->clave }}"
                                                type="number"
                                                name="{{ $baseKey }}[valor]"
                                                min="0"
                                                step="0.01"
                                                value="{{ old($baseKey.'.valor', $tarifa->valor) }}"
                                                data-tarifa-field="{{ $tarifa->clave }}"
                                                class="h-10 w-full rounded border border-slate-300 bg-white px-3 text-sm text-slate-800"
                                            >
                                        </div>

                                        <div class="sm:w-[8.5rem]">
                                            <input type="hidden" name="{{ $baseKey }}[activo]" value="0">
                                            <label class="flex h-10 items-center justify-between rounded border border-slate-300 bg-white px-3 text-sm text-slate-700">
                                                <span class="font-medium">Activa</span>
                                                <input
                                                    type="checkbox"
                                                    name="{{ $baseKey }}[activo]"
                                                    value="1"
                                                    data-tarifa-active="{{ $tarifa->clave }}"
                                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked(old($baseKey.'.activo', (int) $tarifa->activo) == 1)
                                                >
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Subtotal</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $category['formula'] }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-right shadow-sm">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Subtotal {{ $category['title'] }}</p>
                                <p class="text-base font-bold text-slate-800" data-category-total="{{ $categoryKey }}">$ 0</p>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                Si una tarifa esta inactiva, no se precarga al crear clientes nuevos y el boton de restaurar la dejara vacia en el formulario.
            </div>

            <div class="sticky bottom-4 z-10">
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-300 bg-slate-900 px-4 py-4 text-white shadow-2xl lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">Total general configurado</p>
                        <p class="mt-1 text-2xl font-bold" id="tarifas_total_general">$ 0</p>
                        <p class="mt-1 text-xs text-slate-300">Se actualiza en vivo con las tarifas activas de todas las categorias.</p>
                    </div>
                    <button type="submit" class="rounded bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400">
                        Guardar tarifas
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('form[action="{{ route('configuracion.tarifas.update') }}"]');

            if (!form) {
                return;
            }

            const categorySections = Array.from(document.querySelectorAll('[data-tarifa-category]'));
            const totalGeneralElement = document.getElementById('tarifas_total_general');

            if (!categorySections.length || !totalGeneralElement) {
                return;
            }

            const currency = new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            });

            const toNumber = (value) => {
                if (value === null || value === undefined || value === '') {
                    return 0;
                }

                const normalized = String(value).replace(',', '.');
                const parsed = Number.parseFloat(normalized);

                return Number.isFinite(parsed) ? parsed : 0;
            };

            const valueOf = (field) => {
                const input = form.querySelector(`[data-tarifa-field="${field}"]`);
                const active = form.querySelector(`[data-tarifa-active="${field}"]`);

                if (!input || !active || !active.checked) {
                    return 0;
                }

                return toNumber(input.value);
            };

            const formulas = {
                principal: () => {
                    const valorPrincipal = valueOf('vlrprincipal');
                    const numeroEquipos = valueOf('numequipos');
                    const valorTerminal = valueOf('vlrterminal');
                    const equiposAdicionales = Math.max(numeroEquipos - 1, 0);

                    return valorPrincipal + (valorTerminal * equiposAdicionales);
                },
                equipos_extra: () => {
                    const numeroExtra = valueOf('numextra');
                    const valorEquipoExtra = valueOf('vlrextrae');
                    const otroValorExtra = valueOf('vlrextra');

                    return (valorEquipoExtra * numeroExtra) + otroValorExtra;
                },
                nomina: () => {
                    const valorNomina = valueOf('vlrnomina');
                    const numeroNomina = valueOf('nominaterminal');
                    const valorTerminalNomina = valueOf('vlrterminal_nomina');
                    const equiposNominaAdicionales = Math.max(numeroNomina - 1, 0);

                    return valorNomina + (valorTerminalNomina * equiposNominaAdicionales);
                },
                facturacion: () => valueOf('vlrfactura') + valueOf('vlrsoporte') + valueOf('vlrrecepcion'),
                moviles: () => valueOf('vlrmovil') * valueOf('numeromoviles'),
                otros: () => valueOf('vlrterminal_recepcion'),
            };

            const syncTotals = () => {
                let totalGeneral = 0;

                categorySections.forEach((section) => {
                    const category = section.dataset.tarifaCategory;
                    const totalElement = section.querySelector(`[data-category-total="${category}"]`);
                    const calculate = formulas[category] ?? (() => 0);
                    const subtotal = calculate();

                    totalGeneral += subtotal;

                    if (totalElement) {
                        totalElement.textContent = currency.format(subtotal);
                    }
                });

                totalGeneralElement.textContent = currency.format(totalGeneral);
            };

            form.querySelectorAll('[data-tarifa-field], [data-tarifa-active]').forEach((input) => {
                input.addEventListener('input', syncTotals);
                input.addEventListener('change', syncTotals);
            });

            syncTotals();
        })();
    </script>
@endpush
