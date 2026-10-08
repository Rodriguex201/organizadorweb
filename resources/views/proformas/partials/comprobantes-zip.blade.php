<details class="mb-6 rounded-lg bg-white p-4 shadow">
    <summary class="cursor-pointer font-semibold text-indigo-700">Descargar comprobantes</summary>
    <form id="comprobantes-zip-form" action="{{ route('proformas.dashboard.comprobantes') }}" method="POST" class="mt-4 flex flex-wrap items-end gap-4">
        @csrf
        <p class="w-full text-sm text-slate-600">Período de la proforma</p>
        <div>
            <label for="comprobantes-zip-mes" class="block text-sm">Mes</label>
            <select id="comprobantes-zip-mes" name="mes" required class="rounded border px-3 py-2">
                @foreach(\App\Services\ProformasService::MESES as $numero => $nombre)
                    <option value="{{ $numero }}" @selected((int) ($filters['mes'] ?? now()->month) === $numero)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="comprobantes-zip-anio" class="block text-sm">Año</label>
            <input id="comprobantes-zip-anio" name="anio" type="number" min="1900" max="9999" required value="{{ $filters['anio'] ?? now()->year }}" class="w-28 rounded border px-3 py-2">
        </div>
        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50">Descargar ZIP</button>
        <p id="comprobantes-zip-resultado" class="w-full text-sm" role="status" aria-live="polite"></p>
    </form>
</details>
<script>
document.getElementById('comprobantes-zip-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;
    const status = document.getElementById('comprobantes-zip-resultado');
    const data = new FormData(form);
    button.disabled = true;
    status.textContent = 'Preparando comprobantes...';
    try {
        const response = await fetch(form.action, {method: 'POST', body: data, headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'No se pudo generar el ZIP.');
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `comprobantes_${String(data.get('mes')).padStart(2, '0')}_${data.get('anio')}.zip`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        const missing = Number(response.headers.get('X-Comprobantes-Faltantes') || 0);
        status.textContent = `ZIP generado: ${response.headers.get('X-Comprobantes-Incluidos')} comprobantes incluidos. `
            + (missing ? `${missing} faltantes o inaccesibles. Consulta incidencias.txt dentro del ZIP.` : 'Sin archivos faltantes.');
    } catch (error) {
        status.textContent = error.message || 'No se pudo descargar el ZIP.';
    } finally {
        button.disabled = false;
    }
});
</script>
