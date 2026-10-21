<section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="retenciones-heading">
    <div>
        <h2 id="retenciones-heading" class="text-lg font-semibold">Retención en la fuente</h2>
        <p class="text-sm text-slate-600">Catálogo de porcentajes asignables a clientes. Esta configuración no modifica totales de proformas.</p>
    </div>
    @php($retencionForm = (string) old('retencion_form', ''))
    <form method="POST" action="{{ route('configuracion.tarifas.retenciones.store') }}" class="grid gap-3 rounded-lg bg-slate-50 p-4 md:grid-cols-4">
        @csrf
        <input type="hidden" name="retencion_form" value="crear">
        <label class="text-sm">Porcentaje
            <input name="porcentaje" type="number" min="0" max="100" step="0.001" required value="{{ $retencionForm === 'crear' ? old('porcentaje') : '' }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </label>
        <label class="text-sm">Descripción
            <input name="descripcion" maxlength="100" value="{{ $retencionForm === 'crear' ? old('descripcion') : '' }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </label>
        <label class="text-sm">Estado
            <select name="activo" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                <option value="1" @selected($retencionForm !== 'crear' || (string) old('activo', '1') === '1')>Activo</option>
                <option value="0" @selected($retencionForm === 'crear' && (string) old('activo') === '0')>Inactivo</option>
            </select>
        </label>
        <button class="self-end rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white" type="submit">Crear retención</button>
    </form>
    @forelse($retenciones as $retencion)
        @php($editing = $retencionForm === (string) $retencion->id)
        <div class="rounded-lg border border-slate-200 p-4">
            <form method="POST" action="{{ route('configuracion.tarifas.retenciones.update', $retencion->id) }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="retencion_form" value="{{ $retencion->id }}">
                <label class="text-sm">Porcentaje
                    <input name="porcentaje" type="number" min="0" max="100" step="0.001" required value="{{ $editing ? old('porcentaje') : $retencion->porcentaje }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                </label>
                <label class="text-sm">Descripción
                    <input name="descripcion" maxlength="100" value="{{ $editing ? old('descripcion') : $retencion->descripcion }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                </label>
                <label class="text-sm">Estado
                    <select name="activo" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                        <option value="1" @selected((string) ($editing ? old('activo') : $retencion->activo) === '1')>Activo</option>
                        <option value="0" @selected((string) ($editing ? old('activo') : $retencion->activo) === '0')>Inactivo</option>
                    </select>
                </label>
                <button type="submit" class="self-end rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Guardar retención</button>
            </form>
            <form method="POST" action="{{ route('configuracion.tarifas.retenciones.estado', $retencion->id) }}" class="mt-3 text-right">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activo" value="{{ $retencion->activo ? '0' : '1' }}">
                <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm">{{ $retencion->activo ? 'Desactivar' : 'Activar' }}</button>
            </form>
        </div>
    @empty
        <p class="text-sm text-slate-500">No hay retenciones configuradas.</p>
    @endforelse
</section>
