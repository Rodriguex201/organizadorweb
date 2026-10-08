<div id="eventos-independiente-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="eventos-independiente-titulo">
    <div class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl">
        <h2 id="eventos-independiente-titulo" class="text-lg font-semibold">Activación eventos</h2>
        <form id="eventos-independiente-busqueda" class="my-4 flex gap-2">
            <label class="flex-1">Buscar empresa/código
                <input name="q" required minlength="2" maxlength="100" class="block w-full rounded border p-2" placeholder="B543">
            </label>
            <button class="rounded bg-cyan-100 px-3" type="submit">Buscar</button>
        </form>
        <div id="eventos-independiente-resultados" class="max-h-48 overflow-auto"></div>
        <form id="eventos-independiente-form" class="hidden mt-4 space-y-3">
            <p>Empresa/código: <strong id="eventos-independiente-empresa"></strong></p>
            <p>Vencimiento actual: <span id="eventos-independiente-actual"></span></p>
            <label class="block">Nueva fecha de vencimiento
                <input name="fecha_fin" type="date" required class="block rounded border p-2">
            </label>
            <button type="submit" class="rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50">Actualizar vencimiento</button>
        </form>
        <p id="eventos-independiente-estado" role="status" aria-live="polite" class="my-3 text-sm"></p>
        <button id="eventos-independiente-cerrar" type="button" class="rounded bg-slate-200 px-3 py-2">Cancelar</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const get = name => document.getElementById('eventos-independiente-' + name);
    const modal = get('modal'), form = get('form'), search = get('busqueda'), results = get('resultados'), status = get('estado');
    const date = form.elements.fecha_fin, save = form.querySelector('button');
    let company = null, busy = false, version = 0;
    const urls = {search: @json(route('proformas.eventos.buscar')), detail: @json(route('proformas.eventos.detalle')), save: @json(route('proformas.eventos.guardar'))};
    const request = async (url, options = {}) => {
        const response = await fetch(url, {...options, headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}});
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible completar la operación.');
        return payload;
    };
    const clear = () => { company = null; form.classList.add('hidden'); };
    get('abrir').addEventListener('click', () => {
        version++; clear(); results.replaceChildren(); status.textContent = ''; search.reset();
        modal.classList.remove('hidden'); modal.classList.add('flex'); search.elements.q.focus();
    });
    const close = () => {
        if (busy) return;
        version++; clear(); modal.classList.add('hidden'); modal.classList.remove('flex'); get('abrir').focus();
    };
    get('cerrar').addEventListener('click', close);
    modal.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    search.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        const current = ++version; clear(); results.replaceChildren(); status.textContent = 'Buscando...';
        try {
            const payload = await request(urls.search + '?' + new URLSearchParams({q:search.elements.q.value}));
            if (current !== version) return;
            status.textContent = payload.data.length ? '' : 'Empresa no encontrada en Eventos.';
            for (const item of payload.data) {
                const button = document.createElement('button'); button.type = 'button';
                button.className = 'block w-full rounded border p-2 text-left disabled:opacity-50';
                button.textContent = item.empresa + (Number(item.coincidencias) > 1 ? ' — Registros ambiguos; actualización bloqueada' : '');
                button.disabled = Number(item.coincidencias) !== 1;
                button.addEventListener('click', async () => {
                    if (busy) return;
                    const selected = ++version; clear(); status.textContent = 'Consultando...';
                    try {
                        const detail = await request(urls.detail + '?' + new URLSearchParams({empresa:item.empresa}));
                        if (selected !== version) return;
                        company = detail.data.empresa; get('empresa').textContent = company;
                        get('actual').textContent = detail.data.fecha_vencimiento_actual || 'Sin fecha';
                        date.value = detail.data.fecha_vencimiento_actual || ''; form.classList.remove('hidden'); status.textContent = ''; date.focus();
                    } catch (error) { if (selected === version) status.textContent = error.message; }
                });
                results.append(button);
            }
        } catch (error) { if (current === version) status.textContent = error.message; }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !company || !form.reportValidity()) return;
        busy = true; save.disabled = true; get('cerrar').disabled = true; status.textContent = 'Guardando...';
        try {
            const payload = await request(urls.save, {method:'POST', body:JSON.stringify({empresa:company, fecha_fin:date.value})});
            get('actual').textContent = payload.data.fecha_vencimiento_nueva; date.value = payload.data.fecha_vencimiento_nueva;
            status.textContent = payload.data.sin_cambios ? payload.message : 'Fecha de vencimiento actualizada correctamente.';
        } catch (error) { status.textContent = error.message; }
        finally { busy = false; save.disabled = false; get('cerrar').disabled = false; }
    });
});
</script>
