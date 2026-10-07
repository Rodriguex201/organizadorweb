@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const get = id => document.getElementById('whatsapp-' + id);
    const modal = get('modal'), open = get('abrir');
    if (!modal || !open) return;
    const urls = {
        index: @json(route('proformas.whatsapp.index')),
        search: @json(route('proformas.whatsapp.buscar')),
        store: @json(route('proformas.whatsapp.store', ['clienteId' => '__ID__'])),
        update: @json(route('proformas.whatsapp.update', ['clienteId' => '__ID__'])),
        state: @json(route('proformas.whatsapp.estado', ['clienteId' => '__ID__'])),
    };
    const csrf = @json(csrf_token());
    let group = '', page = 1, pages = 1, busy = false, selected = null, editing = false;
    const node = (tag, value, classes = '') => {
        const el = document.createElement(tag); el.textContent = value; el.className = classes; return el;
    };
    function controls() {
        modal.querySelectorAll('button, input, select').forEach(el => el.disabled = busy);
        get('anterior').disabled = busy || page <= 1;
        get('siguiente').disabled = busy || page >= pages;
    }
    async function request(url, method = 'GET', data) {
        const response = await fetch(url, {method, cache: 'no-store', headers: {
            Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf,
        }, ...(data ? {body: JSON.stringify(data)} : {})});
        if (response.redirected || [401, 403, 419].includes(response.status)) throw Error('Sesión no disponible o sin permiso. Recarga la página.');
        const payload = await response.json();
        if (!response.ok) throw Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'No se pudo completar la operación.');
        return payload;
    }
    async function run(action) {
        if (busy) return;
        busy = true; controls(); get('estado').textContent = 'Procesando…';
        try { await action(); }
        catch (error) { get('estado').textContent = error.message; }
        finally { busy = false; controls(); }
    }
    function action(label, fn) {
        const button = node('button', label, 'm-1 rounded border px-2 py-1 text-emerald-800');
        button.type = 'button'; button.addEventListener('click', fn); return button;
    }
    function alternate() {
        const show = get('fuente').value === 'ALTERNATIVO';
        get('alternativo-campo').classList.toggle('hidden', !show);
        get('alternativo').required = show;
    }
    function edit(item, existing) {
        selected = item; editing = existing;
        get('editor-titulo').textContent = (existing ? 'Editar: ' : 'Agregar: ') + item.codigo + ' — ' + item.empresa;
        get('grupo').value = String(item.grupo_fecha || group || 7);
        get('fuente').value = item.telefono_fuente || (item.celular1 ? 'CELULAR1' : item.celular2 ? 'CELULAR2' : 'ALTERNATIVO');
        get('alternativo').value = item.whatsapp_alternativo || '';
        get('telefonos').textContent = 'Celular 1: ' + (item.celular1 || 'Sin número') + ' · Celular 2: ' + (item.celular2 || 'Sin número');
        get('form').classList.remove('hidden'); alternate(); get('grupo').focus();
    }
    async function search() {
        get('resultados').replaceChildren();
        const term = get('buscar').value.trim();
        if (term.length < 2) return;
        const payload = await request(urls.search + '?q=' + encodeURIComponent(term));
        payload.data.forEach(item => {
            const row = node('div', '', 'flex flex-wrap items-center justify-between gap-2 rounded border p-2 text-sm');
            row.append(node('span', item.codigo + ' | ' + item.empresa + ' | NIT ' + item.nit + ' | Cel. 1: ' + (item.celular1 || '—') + ' | Cel. 2: ' + (item.celular2 || '—')));
            if (item.configurado) row.append(node('span', item.activo ? 'Ya agregado' : 'Ya agregado (inactivo): reactívalo en la lista'));
            else row.append(action('Agregar', () => edit(item, false)));
            get('resultados').append(row);
        });
        if (!payload.data.length) get('resultados').append(node('p', 'No se encontraron clientes.'));
    }
    async function list() {
        const payload = await request(urls.index + '?grupo=' + group + '&pagina=' + page);
        page = payload.pagina; pages = payload.paginas;
        get('lista').replaceChildren();
        payload.data.forEach(item => {
            const row = node('tr', '', 'border-b');
            [item.codigo, item.empresa, item.whatsapp + (item.telefono_valido ? '' : ' — Número pendiente'), 'Grupo ' + item.grupo_fecha, item.activo ? 'Activo' : 'Inactivo'].forEach(value => row.append(node('td', value, 'p-2')));
            const actions = node('td', '', 'p-2');
            actions.append(action('Editar', () => edit(item, true)), action(item.activo ? 'Desactivar' : 'Reactivar', () => run(async () => {
                await request(urls.state.replace('__ID__', item.cliente_id), 'PATCH', {activo: !item.activo});
                get('form').classList.add('hidden'); selected = null;
                await list(); await search(); get('estado').textContent = item.activo ? 'Cliente desactivado.' : 'Cliente reactivado.';
            })));
            row.append(actions); get('lista').append(row);
        });
        if (!payload.data.length) {
            const row = node('tr', ''); const cell = node('td', 'No hay clientes configurados en este grupo.', 'p-3'); cell.colSpan = 6; row.append(cell); get('lista').append(row);
        }
        get('pagina').textContent = payload.total + ' cliente(s) · Página ' + page + ' de ' + pages;
    }
    const close = () => {
        if (busy) return;
        modal.classList.add('hidden'); modal.classList.remove('flex'); open.focus();
    };
    open.addEventListener('click', () => {
        modal.classList.remove('hidden'); modal.classList.add('flex'); get('cerrar').focus();
        run(async () => { await list(); get('estado').textContent = ''; });
    });
    get('cerrar').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key === 'Tab') {
            const focusable = [...modal.querySelectorAll('button, input, select')].filter(el => !el.disabled && el.getClientRects().length);
            const first = focusable[0], last = focusable.at(-1);
            if (!first) { event.preventDefault(); return; }
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    get('fuente').addEventListener('change', alternate);
    get('cancelar').addEventListener('click', () => { selected = null; get('form').classList.add('hidden'); });
    get('busqueda').addEventListener('submit', event => {
        event.preventDefault(); run(async () => { await search(); get('estado').textContent = get('buscar').value.trim().length < 2 ? 'Escribe al menos dos caracteres.' : 'Hasta 20 resultados. Afina la búsqueda si no encuentras al cliente.'; });
    });
    modal.querySelectorAll('[data-whatsapp-grupo]').forEach(button => button.addEventListener('click', () => run(async () => {
        const previous = group; group = button.dataset.whatsappGrupo; page = 1;
        try { await list(); } catch (error) { group = previous; throw error; }
        modal.querySelectorAll('[data-whatsapp-grupo]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.whatsappGrupo === group)));
        get('estado').textContent = ''; selected = null; get('form').classList.add('hidden');
    })));
    get('anterior').addEventListener('click', () => run(async () => { page--; await list(); get('estado').textContent = ''; }));
    get('siguiente').addEventListener('click', () => run(async () => { page++; await list(); get('estado').textContent = ''; }));
    get('form').addEventListener('submit', event => {
        event.preventDefault(); if (!selected) return;
        run(async () => {
            await request((editing ? urls.update : urls.store).replace('__ID__', selected.cliente_id), editing ? 'PATCH' : 'POST', {
                grupo_fecha: Number(get('grupo').value), telefono_fuente: get('fuente').value,
                whatsapp_alternativo: get('fuente').value === 'ALTERNATIVO' ? get('alternativo').value : null,
            });
            const savedGroup = get('grupo').value;
            selected = null; get('form').classList.add('hidden');
            await list(); await search();
            get('estado').textContent = 'Configuración guardada.' + (group && group !== savedGroup ? ' Cambia el filtro para verla en el grupo ' + savedGroup + '.' : '');
        });
    });
});
</script>
@endpush
