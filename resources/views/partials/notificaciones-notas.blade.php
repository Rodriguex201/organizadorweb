<div id="notificaciones-notas" class="relative">
    <button id="notificaciones-notas-abrir" type="button" aria-label="Notificaciones de notas" aria-expanded="false" aria-controls="notificaciones-notas-panel" class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-white px-3 py-2 text-indigo-700 hover:bg-indigo-50">
        <span aria-hidden="true">🔔</span>
        <span id="notificaciones-notas-contador" class="text-xs font-semibold" aria-live="polite">…</span>
    </button>
    <section id="notificaciones-notas-panel" aria-labelledby="notificaciones-notas-titulo" class="fixed inset-x-4 top-24 z-50 hidden rounded-lg border border-slate-200 bg-white shadow-xl sm:absolute sm:inset-x-auto sm:left-0 sm:top-full sm:mt-2 sm:w-96">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h2 id="notificaciones-notas-titulo" class="text-sm font-semibold">Notas no leídas</h2>
            <button id="notificaciones-notas-cerrar" type="button" aria-label="Cerrar notificaciones" class="rounded px-2 py-1 text-slate-600">×</button>
        </div>
        <p id="notificaciones-notas-estado" role="status" class="px-4 py-2 text-sm text-slate-600">Cargando…</p>
        <div id="notificaciones-notas-lista" class="max-h-[55vh] divide-y overflow-y-auto"></div>
        <div id="notificaciones-notas-paginacion" class="flex items-center justify-between border-t px-4 py-2 text-xs">
            <button id="notificaciones-notas-anterior" type="button" class="rounded px-2 py-1 text-indigo-700 disabled:opacity-40">Anterior</button>
            <span id="notificaciones-notas-pagina"></span>
            <button id="notificaciones-notas-siguiente" type="button" class="rounded px-2 py-1 text-indigo-700 disabled:opacity-40">Siguiente</button>
        </div>
    </section>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('notificaciones-notas');
    if (!root) return;
    const get = suffix => document.getElementById('notificaciones-notas-' + suffix);
    const button = get('abrir'), panel = get('panel'), status = get('estado'), list = get('lista');
    const indexUrl = @json(route('notificaciones.notas.index'));
    const readUrl = @json(route('notificaciones.notas.leida', ['notaId' => '__ID__']));
    const csrf = @json(csrf_token());
    let page = 1, pages = 1, busy = false, timer = null, stopped = false, opened = false;

    function text(tag, value, classes) {
        const element = document.createElement(tag);
        element.textContent = value;
        element.className = classes;
        return element;
    }
    function controls() {
        get('anterior').disabled = busy || stopped || page <= 1;
        get('siguiente').disabled = busy || stopped || page >= pages;
        list.querySelectorAll('button').forEach(item => item.disabled = busy || stopped);
    }
    function schedule() {
        clearTimeout(timer);
        if (!document.hidden && !stopped) timer = setTimeout(refresh, 30000);
    }
    async function request(url, method = 'GET') {
        const response = await fetch(url, { method, cache: 'no-store', headers: {
            'Accept': 'application/json', 'X-CSRF-TOKEN': csrf,
        }});
        if (response.redirected || [401, 403, 419].includes(response.status)) {
            stopped = true;
            throw new Error('Sesión no disponible. Recargue la página para continuar.');
        }
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar las notificaciones.');
        return payload;
    }
    function render(payload) {
        page = payload.pagina; pages = payload.paginas;
        get('contador').textContent = String(payload.no_leidas);
        button.setAttribute('aria-label', 'Notificaciones de notas: ' + payload.no_leidas + ' no leídas');
        get('pagina').textContent = page + ' / ' + pages;
        status.textContent = payload.no_leidas ? payload.no_leidas + ' nota(s) sin leer' : 'No tienes notas nuevas.';
        list.replaceChildren();
        payload.notificaciones.forEach(note => {
            const item = text('article', '', 'space-y-2 px-4 py-3');
            item.append(text('p', note.cliente, 'break-words text-sm font-semibold text-slate-800'));
            item.append(text('p', note.autor + ' · ' + (note.tipo === 'GENERAL' ? 'Nota general' : 'Tarea / checklist'), 'text-xs text-indigo-700'));
            item.append(text('p', note.resumen, 'break-words text-sm text-slate-700'));
            item.append(text('p', note.fecha, 'text-xs text-slate-500'));
            const read = text('button', 'Marcar como leída', 'rounded bg-indigo-50 px-2 py-1 text-xs text-indigo-700');
            read.type = 'button';
            read.addEventListener('click', async () => {
                if (busy || stopped) return;
                busy = true; clearTimeout(timer); controls();
                try {
                    await request(readUrl.replace('__ID__', note.id), 'PATCH');
                    await load();
                } catch (error) { status.textContent = error.message; }
                finally { busy = false; controls(); schedule(); }
            });
            item.append(read); list.append(item);
        });
    }
    async function load() { render(await request(indexUrl + '?pagina=' + page)); }
    async function refresh() {
        if (document.hidden || stopped) return;
        if (busy) { schedule(); return; }
        busy = true; controls();
        try { await load(); }
        catch (error) {
            status.textContent = error.message;
            get('contador').textContent = '!';
            button.setAttribute('aria-label', 'Notificaciones no disponibles');
        } finally { busy = false; controls(); schedule(); }
    }
    function close() {
        opened = false; panel.classList.add('hidden'); button.setAttribute('aria-expanded', 'false');
    }
    button.addEventListener('click', () => {
        opened = !opened;
        panel.classList.toggle('hidden', !opened);
        button.setAttribute('aria-expanded', String(opened));
        if (opened) { get('cerrar').focus(); refresh(); }
    });
    get('cerrar').addEventListener('click', () => { close(); button.focus(); });
    get('anterior').addEventListener('click', () => { if (!busy && page > 1) { page--; refresh(); } });
    get('siguiente').addEventListener('click', () => { if (!busy && page < pages) { page++; refresh(); } });
    document.addEventListener('click', event => { if (opened && !root.contains(event.target)) close(); });
    root.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); button.focus(); } });
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (!document.hidden) refresh();
    });
    window.addEventListener('pagehide', () => clearTimeout(timer));
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    refresh();
});
</script>
@endpush
