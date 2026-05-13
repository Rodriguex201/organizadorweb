@extends('layouts.admin')

@section('title', 'Configuración de conceptos')

@php
    $statusType = session('status_type', 'success');
    $alertClasses = match ($statusType) {
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'error' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };

    $oldConceptoId = old('concepto_id');
    $shouldOpenFormModal = $errors->any() && in_array(old('form_mode'), ['create', 'edit'], true);
@endphp

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Configuración de conceptos</h1>
                <p class="text-sm text-slate-600">Administra el catálogo base de la tabla <code>conceptos</code>.</p>
            </div>

            <button
                type="button"
                id="nuevo-concepto-btn"
                class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                + Nuevo concepto
            </button>
        </div>

        @if(session('status'))
            <div class="rounded border px-4 py-3 text-sm {{ $alertClasses }}">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-lg bg-white p-4 shadow">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-900">Listado</h2>
                    <p class="text-sm text-slate-500">Orden inicial por <code>codigo ASC</code>.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    {{ $conceptos->count() }} registros
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Código</th>
                            <th class="px-4 py-3 text-left">Nombre</th>
                            <th class="px-4 py-3 text-left">Cuenta</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($conceptos as $item)
                            @php
                                /** @var \App\Models\Concepto $concepto */
                                $concepto = $item['concepto'];
                                $usage = $item['usage'];
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 align-top font-medium text-slate-900">{{ $concepto->codigo }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div class="min-w-[20rem]">
                                        <p class="font-medium text-slate-900">{{ $concepto->nombre }}</p>
                                        @if($usage['used_in_preview'])
                                            <p class="mt-1 text-xs text-amber-600">Concepto protegido por preview/generación actual.</p>
                                        @elseif($usage['used_in_sg_proford'])
                                            <p class="mt-1 text-xs text-slate-500">Usado {{ $usage['sg_proford_count'] }} vez/veces en <code>sg_proford</code>.</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-700">{{ $concepto->cuenta ?: '-' }}</td>
                                <td class="px-4 py-3 align-top">
                                    @if($concepto->activo)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="concepto-edit-btn inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                                            data-id="{{ $concepto->id }}"
                                            data-codigo="{{ $concepto->codigo }}"
                                            data-nombre="{{ $concepto->nombre }}"
                                            data-cuenta="{{ $concepto->cuenta }}"
                                            data-activo="{{ $concepto->activo ? '1' : '0' }}"
                                        >
                                            Editar
                                        </button>

                                        <form method="POST" action="{{ route('configuracion.conceptos.toggle', $concepto) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded px-3 py-1.5 text-xs font-medium {{ $concepto->activo ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' }}"
                                            >
                                                {{ $concepto->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>

                                        @if($usage['can_delete'])
                                            <button
                                                type="button"
                                                class="concepto-delete-btn inline-flex items-center rounded bg-rose-100 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200"
                                                data-id="{{ $concepto->id }}"
                                                data-codigo="{{ $concepto->codigo }}"
                                                data-nombre="{{ $concepto->nombre }}"
                                            >
                                                Eliminar
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="inline-flex cursor-not-allowed items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-400"
                                                title="Este concepto no se puede eliminar. Solo se puede desactivar."
                                                disabled
                                            >
                                                Solo desactivar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay conceptos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        id="concepto-form-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 px-4 py-6"
        aria-hidden="true"
    >
        <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 id="concepto-form-title" class="text-lg font-semibold text-slate-900">Nuevo concepto</h2>
                    <p id="concepto-form-subtitle" class="mt-1 text-sm text-slate-500">Completa la información del concepto.</p>
                </div>
                <button type="button" class="rounded p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-concepto-form-close aria-label="Cerrar modal">
                    X
                </button>
            </div>

            <form id="concepto-form" method="POST" action="{{ route('configuracion.conceptos.store') }}" class="px-5 py-4">
                @csrf
                <input type="hidden" name="form_mode" id="concepto_form_mode" value="{{ old('form_mode', 'create') }}">
                <input type="hidden" name="concepto_id" id="concepto_id" value="{{ old('concepto_id') }}">
                <input type="hidden" name="_method" id="concepto-form-method" value="">

                @if($errors->any())
                    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        Revisa los campos del formulario antes de guardar.
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="codigo" class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                        <input
                            id="codigo"
                            name="codigo"
                            type="text"
                            maxlength="10"
                            value="{{ old('codigo') }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                        @error('codigo')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="cuenta" class="mb-1 block text-sm font-medium text-slate-700">Cuenta</label>
                        <input
                            id="cuenta"
                            name="cuenta"
                            type="text"
                            maxlength="30"
                            value="{{ old('cuenta') }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                        @error('cuenta')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="nombre" class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            value="{{ old('nombre') }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                        @error('nombre')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-3 rounded border border-slate-200 px-3 py-2">
                            <input
                                id="activo"
                                name="activo"
                                type="checkbox"
                                value="1"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                @checked(old('form_mode', 'create') === 'create' ? old('activo', true) : old('activo'))
                            >
                            <span class="text-sm text-slate-700">Activo</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-concepto-form-close>
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        id="concepto-delete-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 px-4 py-6"
        aria-hidden="true"
    >
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Eliminar concepto</h2>
                <p id="concepto-delete-subtitle" class="mt-1 text-sm text-slate-500">Esta acción no se puede deshacer.</p>
            </div>

            <form id="concepto-delete-form" method="POST" action="{{ route('configuracion.conceptos.destroy', 0) }}" class="px-5 py-4">
                @csrf
                @method('DELETE')

                <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Si el concepto está en uso, el sistema bloqueará el borrado y solo permitirá desactivarlo.
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-concepto-delete-close>
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center rounded bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                        Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const formModal = document.getElementById('concepto-form-modal');
            const deleteModal = document.getElementById('concepto-delete-modal');
            const form = document.getElementById('concepto-form');
            const deleteForm = document.getElementById('concepto-delete-form');
            const title = document.getElementById('concepto-form-title');
            const subtitle = document.getElementById('concepto-form-subtitle');
            const modeInput = document.getElementById('concepto_form_mode');
            const conceptoIdInput = document.getElementById('concepto_id');
            const methodInput = document.getElementById('concepto-form-method');
            const codigoInput = document.getElementById('codigo');
            const nombreInput = document.getElementById('nombre');
            const cuentaInput = document.getElementById('cuenta');
            const activoInput = document.getElementById('activo');
            const deleteSubtitle = document.getElementById('concepto-delete-subtitle');

            if (!formModal || !deleteModal || !form || !deleteForm || !title || !subtitle || !modeInput || !conceptoIdInput || !methodInput || !codigoInput || !nombreInput || !cuentaInput || !activoInput || !deleteSubtitle) {
                return;
            }

            const storeUrl = @js(route('configuracion.conceptos.store'));
            const updateUrlTemplate = @js(route('configuracion.conceptos.update', ['concepto' => '__ID__']));
            const deleteUrlTemplate = @js(route('configuracion.conceptos.destroy', ['concepto' => '__ID__']));

            const openFormModal = (config) => {
                form.action = config.action;
                modeInput.value = config.mode;
                conceptoIdInput.value = config.id ?? '';
                methodInput.value = config.method ?? '';
                title.textContent = config.title;
                subtitle.textContent = config.subtitle;
                codigoInput.value = config.codigo ?? '';
                nombreInput.value = config.nombre ?? '';
                cuentaInput.value = config.cuenta ?? '';
                activoInput.checked = Boolean(config.activo);
                formModal.classList.remove('hidden');
                formModal.classList.add('flex');
                formModal.setAttribute('aria-hidden', 'false');
            };

            const closeFormModal = () => {
                formModal.classList.add('hidden');
                formModal.classList.remove('flex');
                formModal.setAttribute('aria-hidden', 'true');
            };

            const openDeleteModal = ({ id, codigo, nombre }) => {
                deleteForm.action = deleteUrlTemplate.replace('__ID__', id);
                deleteSubtitle.textContent = `Confirma la eliminación de ${codigo} - ${nombre}.`;
                deleteModal.classList.remove('hidden');
                deleteModal.classList.add('flex');
                deleteModal.setAttribute('aria-hidden', 'false');
            };

            const closeDeleteModal = () => {
                deleteModal.classList.add('hidden');
                deleteModal.classList.remove('flex');
                deleteModal.setAttribute('aria-hidden', 'true');
            };

            document.getElementById('nuevo-concepto-btn')?.addEventListener('click', () => {
                openFormModal({
                    action: storeUrl,
                    mode: 'create',
                    method: '',
                    title: 'Nuevo concepto',
                    subtitle: 'Completa la información del concepto.',
                    codigo: '',
                    nombre: '',
                    cuenta: '',
                    activo: true,
                });
            });

            document.querySelectorAll('.concepto-edit-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    openFormModal({
                        action: updateUrlTemplate.replace('__ID__', button.dataset.id),
                        mode: 'edit',
                        id: button.dataset.id,
                        method: 'PUT',
                        title: 'Editar concepto',
                        subtitle: `Actualiza la configuración del concepto ${button.dataset.codigo}.`,
                        codigo: button.dataset.codigo ?? '',
                        nombre: button.dataset.nombre ?? '',
                        cuenta: button.dataset.cuenta ?? '',
                        activo: button.dataset.activo === '1',
                    });
                });
            });

            document.querySelectorAll('.concepto-delete-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    openDeleteModal({
                        id: button.dataset.id,
                        codigo: button.dataset.codigo ?? '',
                        nombre: button.dataset.nombre ?? '',
                    });
                });
            });

            document.querySelectorAll('[data-concepto-form-close]').forEach((button) => {
                button.addEventListener('click', closeFormModal);
            });

            document.querySelectorAll('[data-concepto-delete-close]').forEach((button) => {
                button.addEventListener('click', closeDeleteModal);
            });

            formModal.addEventListener('click', (event) => {
                if (event.target === formModal) {
                    closeFormModal();
                }
            });

            deleteModal.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    closeDeleteModal();
                }
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (!formModal.classList.contains('hidden')) {
                        closeFormModal();
                    }

                    if (!deleteModal.classList.contains('hidden')) {
                        closeDeleteModal();
                    }
                }
            });

            @if($shouldOpenFormModal)
                @if(old('form_mode') === 'edit' && $oldConceptoId)
                    const originalTrigger = document.querySelector(`.concepto-edit-btn[data-id="{{ $oldConceptoId }}"]`);
                    openFormModal({
                        action: originalTrigger?.dataset.id
                            ? updateUrlTemplate.replace('__ID__', originalTrigger.dataset.id)
                            : updateUrlTemplate.replace('__ID__', @js($oldConceptoId)),
                        mode: 'edit',
                        id: @js($oldConceptoId),
                        method: 'PUT',
                        title: 'Editar concepto',
                        subtitle: originalTrigger?.dataset.codigo
                            ? `Actualiza la configuración del concepto ${originalTrigger.dataset.codigo}.`
                            : 'Actualiza la configuración del concepto.',
                        codigo: @js(old('codigo')),
                        nombre: @js(old('nombre')),
                        cuenta: @js(old('cuenta')),
                        activo: @js((bool) old('activo')),
                    });
                @else
                    openFormModal({
                        action: storeUrl,
                        mode: 'create',
                        method: '',
                        title: 'Nuevo concepto',
                        subtitle: 'Completa la información del concepto.',
                        codigo: @js(old('codigo')),
                        nombre: @js(old('nombre')),
                        cuenta: @js(old('cuenta')),
                        activo: @js(old('activo', true)),
                    });
                @endif
            @endif
        })();
    </script>
@endpush
