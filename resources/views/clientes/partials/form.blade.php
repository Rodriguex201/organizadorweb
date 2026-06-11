@php
    $cliente = $cliente ?? null;
    $catalogos = $catalogos ?? [];
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $stepOneFields = ['nit', 'dv', 'nombre', 'codigo', 'empresa', 'celular1', 'email', 'departamento', 'ciudad_codigo', 'fecha_inicio', 'fecha_arriendo', 'ip_empresa', 'clase', 'modalidad', 'regimen', 'tipo_cliente_id', 'llego', 'estado_facturacion'];
    $stepOneHasErrors = collect($stepOneFields)->contains(fn (string $field): bool => $errors->has($field));
    $initialStep = old('wizard_step', $errors->any() && !$stepOneHasErrors ? '2' : '1');

    $value = static function (string $input, ?string $column = null) use ($cliente) {
        $fallback = $column && $cliente ? ($cliente->{$column} ?? null) : null;

        return old($input, $fallback);
    };

    $fieldUnavailable = static fn (?string $column): bool => $column === null;
@endphp

@if($errors->has('general'))
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ $errors->first('general') }}
    </div>
@endif

@if($errors->any() && !$errors->has('general'))
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <p class="font-medium">Revisa los campos del formulario:</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<input type="hidden" name="wizard_step" id="wizard_step" value="{{ $initialStep }}">

<div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3">
            <div data-step-badge="1" class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">1</div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Datos generales cliente</p>
                <p class="text-xs text-slate-500">InformaciÃ³n principal del cliente antes de revisar tarifas.</p>
            </div>
        </div>
        <div class="hidden h-px flex-1 bg-slate-200 md:block"></div>
        <div class="flex items-center gap-3">
            <div data-step-badge="2" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-500">2</div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Valores proforma</p>
                <p class="text-xs text-slate-500">Ajusta tarifas y confirma el valor total antes de actualizar.</p>
            </div>
        </div>
    </div>
</div>

<section data-step-panel="1">
    @include('clientes.partials.basic-fields', [
        'cliente' => $cliente,
        'catalogos' => $catalogos,
        'mapping' => $mapping,
        'value' => $value,
        'fieldUnavailable' => $fieldUnavailable,
        'estadosFacturacion' => $estadosFacturacion,
        'selectedCity' => $selectedCity ?? null,
    ])

    <p class="mt-4 text-xs text-slate-500">Los campos deshabilitados no existen aÃºn en la tabla <code>clientes_potenciales</code> de esta instancia y se muestran como fallback visual.</p>

    <div class="mt-6 flex items-center gap-3">
        <button type="button" id="wizard_next_button" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Siguiente</button>
        <a href="{{ route('clientes.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver</a>
    </div>
</section>

<section data-step-panel="2" class="hidden">
    @include('clientes.partials.proforma-fields', [
        'cliente' => $cliente,
        'mapping' => $mapping,
        'value' => $value,
        'fieldUnavailable' => $fieldUnavailable,
        'tarifasDefaults' => [],
    ])

    <div class="mt-6 flex items-center gap-3">
        <button type="button" id="wizard_back_button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver</button>
        <button type="submit" id="wizard_submit_button" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Actualizar</button>
    </div>
</section>

@include('clientes.partials.form-scripts')
