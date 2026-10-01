@extends('layouts.admin')

@section('title', 'Cadena — Cargar originales')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <p class="text-sm text-slate-500"><a href="{{ route('configuracion.importaciones.index') }}" class="underline">Importaciones</a> / Cadena / Cargar originales</p>
        <h1 class="text-2xl font-bold">Cadena — Cargar originales</h1>
        <p class="mt-2 text-sm text-slate-600">Selecciona el período, carga los originales y genera directamente el resumen o el ZIP.</p>
    </div>
    <div class="rounded border border-blue-200 bg-blue-50 p-4 text-sm">
        Esta pantalla solo consulta y prepara datos. No actualiza cobros, no crea proformas, no genera PDF y no envía correos.
        Las categorías ausentes se conservan. Ninguna cantidad de esta vista reemplaza valores actuales.
    </div>
    @if ($errors->any())
        <ul class="list-inside list-disc rounded border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form id="cadena-form" method="POST" action="{{ route('configuracion.importaciones.cadena.generate') }}" enctype="multipart/form-data" class="space-y-5 rounded bg-white p-5 shadow">
        @csrf
        <div class="flex flex-wrap gap-4">
            <label class="block">Mes
                <select id="cadena-mes" name="mes" required class="block rounded border border-slate-300 p-2">
                    <option value="">Selecciona un mes</option>
                    @foreach ($meses as $mes)
                        <option value="{{ $mes }}" @selected(old('mes', $selectedMes) === $mes)>{{ ucfirst($mes) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">Año
                <input id="cadena-anio" type="number" name="anio" min="2000" max="9999" required value="{{ old('anio', $selectedAnio) }}" class="block w-32 rounded border border-slate-300 p-2">
            </label>
        </div>
        <p id="cadena-periodo" class="font-semibold" aria-live="polite"></p>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['facturas' => 'Facturas y notas — 01, 91, 92', 'soporte' => 'Documento soporte — 05, 95', 'eventos' => 'Eventos — 030 a 034'] as $category => $label)
                <div class="block rounded border border-slate-200 p-3 text-sm">
                    <p id="cadena-{{ $category }}-label">{{ $label }}</p>
                    <input type="file" id="cadena-{{ $category }}" name="{{ $category }}[]" multiple accept=".csv,.xlsx,.xls" aria-labelledby="cadena-{{ $category }}-label" hidden>
                    <button type="button" data-cadena-picker="{{ $category }}" aria-describedby="cadena-{{ $category }}-label cadena-{{ $category }}-count" class="mt-2 rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">{{ $category !== 'eventos' ? 'Seleccionar archivos' : 'Seleccionar archivo' }}</button>
                    @if($category !== 'eventos')
                        <p id="cadena-{{ $category }}-count" class="mt-2" aria-live="polite">0 archivos seleccionados</p>
                        <ul id="cadena-{{ $category }}-list" class="mt-2 space-y-2"></ul>
                        <p id="cadena-{{ $category }}-error" role="alert" class="text-amber-800"></p>
                        <button id="cadena-{{ $category }}-add" type="button" class="mt-2 rounded border p-2">+ Agregar más archivos</button>
                        <button id="cadena-{{ $category }}-clear" type="button" class="mt-2 rounded border p-2">Limpiar selección</button>
                    @else
                        <p id="cadena-{{ $category }}-count" class="mt-2" aria-live="polite">Ningún archivo seleccionado</p>
                        <ul id="cadena-{{ $category }}-list" class="mt-2 space-y-2 break-all"></ul>
                    @endif
                    <div class="mt-2">
                        <button type="submit" data-cadena-generate="{{ $category }}" disabled class="cadena-generate rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">{{ ['facturas' => 'Generar resumen combinado', 'soporte' => 'Generar resumen soporte', 'eventos' => 'Generar resumen eventos'][$category] }}</button>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">CSV, XLSX o XLS. Hasta 5 archivos por categoría y 10 MB por archivo. Todos deben corresponder al período seleccionado; no se deduce del nombre. Se excluye el mismo archivo repetido dentro de una categoría. Un libro con soporte y eventos puede seleccionarse en ambas categorías.</p>
        <p class="text-sm">Cada botón procesa únicamente los archivos de su tarjeta para el período seleccionado.</p>
        <button type="submit" data-cadena-generate="paquete" disabled class="cadena-generate rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">Generar paquete completo</button>
        <p class="text-sm">Requiere archivos en las tres categorías. Descarga los tres resúmenes en un único ZIP. Los errores estructurales bloquean la descarga. Facturas/notas y eventos conservan NIT pendientes; soporte exporta solo clientes resueltos y deja los pendientes en auditoría.</p>
        <a href="{{ route('configuracion.importaciones.cadena.index') }}" class="ml-3 text-sm underline">Limpiar</a>
    </form>
    <section id="cadena-result" hidden tabindex="-1" class="rounded border bg-white p-4" aria-live="polite">
        <p id="cadena-result-message" class="font-semibold"></p>
        <ul id="cadena-result-issues" class="list-inside list-disc text-sm"></ul>
        <a id="cadena-result-download" hidden class="underline">Descargar archivo generado</a>
    </section>
    <p id="cadena-invalidada" hidden class="rounded bg-amber-50 p-4 text-amber-800" role="status">La vista previa anterior fue invalidada. Carga los archivos y prepara una nueva vista para el período seleccionado.</p>
    @if ($preview !== null)
        <div id="cadena-preview">@include('importaciones.cadena.preview')</div>
    @endif
</div>
<style>
    #cadena-form .cadena-generate:disabled { opacity: .45; cursor: not-allowed; }
</style>
<script>
(() => {
    const form = document.getElementById('cadena-form');
    const month = document.getElementById('cadena-mes');
    const year = document.getElementById('cadena-anio');
    const categories = ['facturas', 'soporte', 'eventos'];
    let busy = false;
    let downloadUrl = null;
    const generateButtons = Array.from(form.querySelectorAll('[data-cadena-generate]'));
    function updateGenerationButtons() {
        generateButtons.forEach(button => {
            button.disabled = busy || (button.dataset.cadenaGenerate === 'paquete'
                ? !categories.every(category => document.getElementById('cadena-' + category).files.length > 0)
                : document.getElementById('cadena-' + button.dataset.cadenaGenerate).files.length === 0);
        });
    }
    function invalidate() {
        if (downloadUrl) { URL.revokeObjectURL(downloadUrl); downloadUrl = null; }
        document.getElementById('cadena-result').hidden = true;
        document.getElementById('cadena-result-download').hidden = true;
        const preview = document.getElementById('cadena-preview');
        if (preview) {
            preview.remove();
            document.getElementById('cadena-invalidada').hidden = false;
        }
    }
    function period() {
        document.getElementById('cadena-periodo').textContent = month.value && year.value
            ? 'Período seleccionado: ' + month.options[month.selectedIndex].text + ' ' + year.value
            : 'Selecciona mes y año para continuar.';
    }
    [month, year].forEach(input => input.addEventListener('input', () => { invalidate(); period(); }));
    form.querySelectorAll('input[type=file]').forEach(input => input.addEventListener('change', invalidate));
    form.querySelectorAll('[data-cadena-picker]').forEach(button => {
        const category = button.dataset.cadenaPicker;
        const input = document.getElementById('cadena-' + category);
        button.addEventListener('click', () => input.click());
        const showSelection = () => {
            if (category !== 'eventos') return; // Shared multi-file selector owns invoices and support.
            const files = Array.from(input.files);
            document.getElementById('cadena-' + category + '-count').textContent = files.length
                ? files.length + (files.length === 1 ? ' archivo seleccionado' : ' archivos seleccionados')
                : 'Ningún archivo seleccionado';
            const names = document.getElementById('cadena-' + category + '-list');
            names.replaceChildren();
            files.forEach(file => {
                const item = document.createElement('li');
                item.textContent = file.name;
                names.append(item);
            });
            updateGenerationButtons();
        };
        input.addEventListener('change', showSelection);
        input.addEventListener('cancel', () => { showSelection(); button.focus(); });
        showSelection();
    });
    function setupMultipleFiles(category) {
        const input = document.getElementById('cadena-' + category);
        const list = document.getElementById('cadena-' + category + '-list');
        const error = document.getElementById('cadena-' + category + '-error');
        let selected = Array.from(input.files);
        function renderFiles() {
            const transfer = new DataTransfer();
            selected.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
            updateGenerationButtons();
            document.getElementById('cadena-' + category + '-count').textContent = selected.length + (selected.length === 1 ? ' archivo seleccionado' : ' archivos seleccionados');
            list.replaceChildren();
            selected.forEach((file, index) => {
                const item = document.createElement('li');
                const name = document.createElement('span');
                name.textContent = file.name + ' ';
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'rounded border p-2';
                remove.textContent = 'Quitar';
                remove.setAttribute('aria-label', 'Quitar ' + file.name);
                remove.addEventListener('click', () => {
                    selected.splice(index, 1); error.textContent = ''; invalidate(); renderFiles();
                });
                item.append(name, remove); list.append(item);
            });
        }
        input.addEventListener('change', () => {
            const added = Array.from(input.files);
            if (selected.length + added.length > 5 || added.some(file => file.size > 10240 * 1024)) {
                error.textContent = 'Máximo 5 archivos y 10 MB por archivo. La selección anterior se conserva.';
            } else {
                selected.push(...added); error.textContent = '';
            }
            renderFiles();
        });
        document.getElementById('cadena-' + category + '-add').addEventListener('click', () => input.click());
        document.getElementById('cadena-' + category + '-clear').addEventListener('click', () => {
            selected = []; error.textContent = ''; invalidate(); renderFiles();
        });
    }
    ['facturas', 'soporte'].forEach(setupMultipleFiles);
    let submittingCategory = null;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const category = event.submitter?.dataset.cadenaGenerate;
        submittingCategory = null;
        const ready = category === 'paquete'
            ? categories.every(key => document.getElementById('cadena-' + key).files.length > 0)
            : categories.includes(category) && document.getElementById('cadena-' + category).files.length > 0;
        if (!ready) {
            event.preventDefault();
            updateGenerationButtons();
            return;
        }
        submittingCategory = category;
        invalidate();
        const payload = new FormData(form);
        payload.set('accion', category);
        const result = document.getElementById('cadena-result');
        const message = document.getElementById('cadena-result-message');
        const issues = document.getElementById('cadena-result-issues');
        const link = document.getElementById('cadena-result-download');
        issues.replaceChildren();
        result.hidden = false;
        message.textContent = 'Procesando archivos…';
        busy = true;
        const controls = Array.from(form.querySelectorAll('input, select, button'));
        const priorDisabled = controls.map(control => control.disabled);
        const activeButton = event.submitter;
        const originalLabel = activeButton.textContent;
        const icon = document.createElement('span');
        icon.textContent = '⏳ ';
        icon.setAttribute('aria-hidden', 'true');
        activeButton.replaceChildren(icon, document.createTextNode('Procesando...'));
        activeButton.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');
        const navigationLinks = Array.from(form.querySelectorAll('a'));
        navigationLinks.forEach(link => { link.setAttribute('aria-disabled', 'true'); link.tabIndex = -1; });
        controls.forEach(control => { control.disabled = true; });
        try {
            const response = await fetch(form.action, { method: 'POST', body: payload,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const filename = response.headers.get('X-Cadena-Filename');
            if (!response.ok || !filename) {
                let detail = {};
                if ((response.headers.get('Content-Type') || '').includes('application/json')) detail = await response.json();
                message.textContent = [409, 422, 503].includes(response.status) ? (detail.message || 'Revisa los archivos y el período.')
                    : 'No se pudo generar el archivo. Comprueba tu sesión y vuelve a intentar.';
                const errors = [...(detail.issues || []), ...Object.values(detail.errors || {}).flat()];
                errors.forEach(text => { const item = document.createElement('li'); item.textContent = text; issues.append(item); });
                result.focus();
                return;
            }
            downloadUrl = URL.createObjectURL(await response.blob());
            link.href = downloadUrl;
            link.download = filename;
            link.textContent = 'Descargar ' + filename;
            link.hidden = false;
            message.textContent = 'Archivo generado: ' + filename + '. Descarga iniciada; si no comienza, utiliza el enlace.';
            const pending = Number(response.headers.get('X-Cadena-Pendientes') || 0);
            if (pending > 0) {
                const warning = document.createElement('li');
                warning.textContent = 'Advertencia: ' + pending + ' NIT ambiguos o sin cliente requieren revisión. En facturas/notas y eventos se incluyen sin asignación automática; en soporte quedan únicamente en auditoría.';
                issues.append(warning);
            }
            link.click();
        } catch (error) {
            message.textContent = 'La generación o descarga no terminó. Conservamos tu selección para volver a intentar.';
        } finally {
            busy = false;
            activeButton.textContent = originalLabel;
            activeButton.removeAttribute('aria-busy');
            form.removeAttribute('aria-busy');
            navigationLinks.forEach(link => { link.removeAttribute('aria-disabled'); link.removeAttribute('tabindex'); });
            controls.forEach((control, index) => { control.disabled = priorDisabled[index]; });
            updateGenerationButtons();
        }
    });
    form.querySelectorAll('a').forEach(link => link.addEventListener('click', event => {
        if (busy) event.preventDefault();
    }));
    // Filter the native multipart request, retaining all selections in the UI.
    form.addEventListener('formdata', event => {
        categories.forEach(category => {
            if (submittingCategory !== 'paquete' && category !== submittingCategory) event.formData.delete(category + '[]');
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) invalidate(); period(); updateGenerationButtons(); });
    updateGenerationButtons();
    period();
})();
</script>
@endsection
