@extends('layouts.admin')

@section('title', 'Crear cliente')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Crear cliente / empresa</h1>
        <p class="text-sm text-slate-600">Registro inicial en <code>clientes_potenciales</code>.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        @php
            $cliente = null;
            $value = static function (string $input, ?string $column = null) {
                return old($input);
            };
            $fieldUnavailable = static fn (?string $column): bool => $column === null;
            $initialStep = old('wizard_step', $errors->any() ? '2' : '1');
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

        <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div data-step-badge="1" class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">1</div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Datos básicos cliente</p>
                        <p class="text-xs text-slate-500">Validación inicial sin guardar en BD.</p>
                    </div>
                </div>
                <div class="hidden h-px flex-1 bg-slate-200 md:block"></div>
                <div class="flex items-center gap-3">
                    <div data-step-badge="2" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-500">2</div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Configuración de valores/proforma</p>
                        <p class="text-xs text-slate-500">Guardado final de toda la información.</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('clientes.store') }}" id="cliente-create-form" data-initial-step="{{ $initialStep }}">
            @csrf
            <input type="hidden" name="wizard_step" id="wizard_step" value="{{ $initialStep }}">

            <section data-step-panel="1">
                @include('clientes.partials.basic-fields', [
                    'cliente' => $cliente,
                    'catalogos' => $catalogos,
                    'mapping' => $mapping,
                    'value' => $value,
                    'fieldUnavailable' => $fieldUnavailable,
                ])

                <p class="mt-4 text-xs text-slate-500">Los campos deshabilitados no existen aún en la tabla <code>clientes_potenciales</code> de esta instancia y se muestran como fallback visual.</p>

                <div class="mt-6 flex items-center gap-3">
                    <button type="button" id="wizard_next_button" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Siguiente</button>
                    <a href="{{ route('clientes.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</a>
                </div>
            </section>

            <section data-step-panel="2" class="hidden">
                @include('clientes.partials.proforma-fields', [
                    'cliente' => $cliente,
                    'mapping' => $mapping,
                    'value' => $value,
                    'fieldUnavailable' => $fieldUnavailable,
                ])

                <div class="mt-6 flex items-center gap-3">
                    <button type="button" id="wizard_back_button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver</button>
                    <button type="submit" id="wizard_submit_button" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Guardar cliente</button>
                </div>
            </section>
        </form>
    </div>
</div>
@endsection

@include('clientes.partials.form-scripts')

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('cliente-create-form');

            if (!form) {
                return;
            }

            const stepInput = document.getElementById('wizard_step');
            const panels = {
                1: document.querySelector('[data-step-panel="1"]'),
                2: document.querySelector('[data-step-panel="2"]'),
            };
            const badges = {
                1: document.querySelector('[data-step-badge="1"]'),
                2: document.querySelector('[data-step-badge="2"]'),
            };
            const nextButton = document.getElementById('wizard_next_button');
            const backButton = document.getElementById('wizard_back_button');
            const submitButton = document.getElementById('wizard_submit_button');

            const setStep = (step) => {
                stepInput.value = String(step);

                Object.entries(panels).forEach(([panelStep, panel]) => {
                    if (!panel) {
                        return;
                    }

                    panel.classList.toggle('hidden', Number(panelStep) !== step);
                });

                Object.entries(badges).forEach(([badgeStep, badge]) => {
                    if (!badge) {
                        return;
                    }

                    const active = Number(badgeStep) === step;
                    badge.classList.toggle('bg-indigo-600', active);
                    badge.classList.toggle('text-white', active);
                    badge.classList.toggle('bg-slate-200', !active);
                    badge.classList.toggle('text-slate-500', !active);
                });
            };

            const validateStepOne = () => {
                const fieldSelector = 'input, select, textarea';
                const fields = panels[1]?.querySelectorAll(fieldSelector) ?? [];

                for (const field of fields) {
                    if (!(field instanceof HTMLElement) || field.hasAttribute('disabled')) {
                        continue;
                    }

                    if (typeof field.reportValidity === 'function' && !field.reportValidity()) {
                        return false;
                    }
                }

                return true;
            };

            nextButton?.addEventListener('click', () => {
                if (!validateStepOne()) {
                    return;
                }

                setStep(2);
            });

            backButton?.addEventListener('click', () => setStep(1));
            submitButton?.addEventListener('click', () => setStep(2));

            setStep(Number(form.dataset.initialStep || 1));
        })();
    </script>
@endpush
