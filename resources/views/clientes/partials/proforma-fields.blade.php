@php
    $cliente = $cliente ?? null;
    $value = $value ?? static function (string $input, ?string $column = null) use ($cliente) {
        $fallback = $column && $cliente ? ($cliente->{$column} ?? null) : null;

        return old($input, $fallback);
    };

    $fieldUnavailable = $fieldUnavailable ?? static fn (?string $column): bool => $column === null;
    $tarifasDefaults = $tarifasDefaults ?? [];
    $numberValue = static function (string $input, ?string $column = null) use ($value) {
        $resolved = $value($input, $column);

        return $resolved === null ? '' : $resolved;
    };
    $defaultValue = static function (string $input) use ($tarifasDefaults) {
        $resolved = $tarifasDefaults[$input] ?? null;

        return $resolved === null ? '' : $resolved;
    };

    $proformaFields = [
        ['name' => 'vlrprincipal', 'label' => 'Valor principal', 'column' => $mapping['vlrprincipal'] ?? null, 'step' => '0.01'],
        ['name' => 'numequipos', 'label' => 'Número equipos', 'column' => $mapping['numequipos'] ?? null, 'step' => '1'],
        ['name' => 'vlrterminal', 'label' => 'Valor terminal', 'column' => $mapping['vlrterminal'] ?? null, 'step' => '0.01'],
        ['name' => 'vlrterminal_recepcion', 'label' => 'Vlr terminal recepción', 'column' => $mapping['vlrterminal_recepcion'] ?? null, 'step' => '0.01'],
        ['name' => 'vlrnomina', 'label' => 'Valor principal nómina', 'column' => $mapping['vlrnomina'] ?? null, 'step' => '0.01'],
        ['name' => 'nominaterminal', 'label' => 'Número equipos nómina', 'column' => $mapping['nominaterminal'] ?? null, 'step' => '1'],
        ['name' => 'vlrterminal_nomina', 'label' => 'Valor terminal nómina', 'column' => $mapping['vlrterminal_nomina'] ?? null, 'step' => '0.01'],
        ['name' => 'vlracuse', 'label' => 'Valor recepción', 'column' => $mapping['vlracuse'] ?? null, 'step' => '0.01'],
        ['name' => 'vlrfactura', 'label' => 'Valor factura', 'column' => $mapping['vlrfactura'] ?? null, 'step' => '0.01'],
        ['name' => 'vlrsoporte', 'label' => 'Valor soporte', 'column' => $mapping['vlrsoporte'] ?? null, 'step' => '0.01'],
        ['name' => 'vlrextra', 'label' => 'Otro valor (Extra)', 'column' => $mapping['vlrextra'] ?? null, 'step' => '0.01'],
        ['name' => 'numeromoviles', 'label' => 'Número móviles', 'column' => $mapping['numeromoviles'] ?? null, 'step' => '1'],
        ['name' => 'vlrmovil', 'label' => 'Valor móvil', 'column' => $mapping['vlrmovil'] ?? null, 'step' => '0.01'],
        ['name' => 'numextra', 'label' => 'Número equipos extra', 'column' => $mapping['numextra'] ?? null, 'step' => '1'],
        ['name' => 'vlrextrae', 'label' => 'Valor equipo extra', 'column' => $mapping['vlrextrae'] ?? null, 'step' => '0.01'],
    ];

    $missingProformaFields = collect($proformaFields)
        ->filter(fn (array $field): bool => $fieldUnavailable($field['column']))
        ->pluck('label')
        ->all();
@endphp

<div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Valores proforma</h3>
            <p class="text-xs text-slate-500">Captura compacta tipo administrativo, con cálculo automático del valor total.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if($tarifasDefaults !== [])
                <button
                    type="button"
                    id="restaurar_tarifas_button"
                    class="inline-flex items-center rounded bg-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-300"
                >
                    Restaurar tarifas predeterminadas
                </button>
            @endif
            <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-right shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Valor total</p>
                <p id="valor_total_display" class="text-lg font-bold text-emerald-700">$0</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Base mensual</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach(array_slice($proformaFields, 0, 7) as $field)
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                        <input
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            type="number"
                            min="0"
                            step="{{ $field['step'] }}"
                            value="{{ $numberValue($field['name'], $field['column']) }}"
                            data-proforma-input
                            data-default-value="{{ $defaultValue($field['name']) }}"
                            @disabled($fieldUnavailable($field['column']))
                            class="h-9 w-full rounded-md border border-slate-300 px-2 text-sm disabled:bg-slate-100"
                        >
                        @error($field['name'])
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Valores complementarios</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach(array_slice($proformaFields, 7) as $field)
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                        <input
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            type="number"
                            min="0"
                            step="{{ $field['step'] }}"
                            value="{{ $numberValue($field['name'], $field['column']) }}"
                            data-proforma-input
                            data-default-value="{{ $defaultValue($field['name']) }}"
                            @disabled($fieldUnavailable($field['column']))
                            class="h-9 w-full rounded-md border border-slate-300 px-2 text-sm disabled:bg-slate-100"
                        >
                        @error($field['name'])
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <input type="hidden" id="valor_total" name="valor_total" value="{{ $numberValue('valor_total', $mapping['valor_total'] ?? null) }}">

    <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-xs text-slate-600">
        Fórmula: valor principal + (valor terminal x equipos adicionales) + (valor equipo extra x equipos extra) + valor nómina + (valor móvil x número móviles)
    </div>

    @if($missingProformaFields !== [])
        <p class="mt-3 text-xs text-slate-500">
            Campos no disponibles en esta instancia de <code>clientes_potenciales</code>:
            {{ implode(', ', $missingProformaFields) }}.
        </p>
    @endif
</div>
