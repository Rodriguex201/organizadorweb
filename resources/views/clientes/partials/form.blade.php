@php
    $cliente = $cliente ?? null;
    $catalogos = $catalogos ?? [];

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

@include('clientes.partials.basic-fields', [
    'cliente' => $cliente,
    'catalogos' => $catalogos,
    'mapping' => $mapping,
    'value' => $value,
    'fieldUnavailable' => $fieldUnavailable,
])

<div class="mt-6">
    @include('clientes.partials.proforma-fields', [
        'cliente' => $cliente,
        'mapping' => $mapping,
        'value' => $value,
        'fieldUnavailable' => $fieldUnavailable,
        'tarifasDefaults' => [],
    ])
</div>

<p class="mt-4 text-xs text-slate-500">Los campos deshabilitados no existen aún en la tabla <code>clientes_potenciales</code> de esta instancia y se muestran como fallback visual.</p>

@include('clientes.partials.form-scripts')
