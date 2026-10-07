@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('nota-cobro-modal');
    if (!modal) return;
    const el = id => document.getElementById(id);
    const buttons = [...document.querySelectorAll('.nota-cobro-btn')];
    const form = el('cliente-nota-form');
    const feedback = el('nota-cobro-feedback');
    const legacy = el('nota-cobro-textarea');
    const urls = {
        notas: @json(route('clientes.notas.index', ['clienteId' => '__ID__'])),
        legacy: @json(route('cobros.nota.update', ['id' => '__ID__'])),
    };
    const csrf = @json(csrf_token());
    let clientId = null, context = null, opener = null;
    let busy = false, ready = false, notes = [], editingId = null, editingType = 'GENERAL';

    function message(text, error = false) {
        feedback.textContent = text;
        feedback.classList.toggle('hidden', !text);
        feedback.classList.toggle('text-rose-700', error);
        feedback.classList.toggle('text-emerald-700', !error);
    }
    function controls() {
        modal.querySelectorAll('button, input, textarea').forEach(control => {
            const close = ['nota-cobro-cancelar', 'nota-cobro-cancelar-top'].includes(control.id);
            control.disabled = busy || (!ready && !close);
        });
        modal.setAttribute('aria-busy', String(busy));
    }
    function close() {
        if (busy) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        opener?.focus();
        clientId = null;
    }
    function resetForm() {
        form.reset();
        form.classList.add('hidden');
        editingId = null;
    }
    function edit(type, note = null) {
        if (!ready || busy) return;
        editingId = note?.id ?? null;
        editingType = type;
        el('cliente-nota-form-titulo').textContent = note ? 'Editar ' + (type === 'GENERAL' ? 'nota' : 'tarea') : (type === 'GENERAL' ? 'Nueva nota' : 'Nueva tarea');
        el('cliente-nota-texto').value = note?.texto ?? '';
        el('cliente-nota-monto').value = note?.monto ?? '';
        el('cliente-nota-fecha').value = note?.fecha_objetivo ?? '';
        el('cliente-nota-tarea-campos').classList.toggle('hidden', type !== 'CHECKLIST');
        form.classList.remove('hidden');
        el('cliente-nota-texto').focus();
    }
    async function request(url, method = 'GET', body = undefined) {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Notas-Contexto': context },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            if ([401, 403, 409, 419].includes(response.status)) ready = false;
            const detail = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
            throw new Error(detail || 'No fue posible procesar las notas. Recargue el listado e intente de nuevo.');
        }
        return payload;
    }
    function baseUrl() { return urls.notas.replace('__ID__', clientId); }
    function node(tag, text, className = '') {
        const result = document.createElement(tag);
        result.textContent = text;
        result.className = className;
        return result;
    }
    function action(label, handler, danger = false) {
        const button = node('button', label, 'rounded border px-2 py-1 text-xs ' + (danger ? 'text-rose-700' : 'text-indigo-700'));
        button.type = 'button';
        button.addEventListener('click', handler);
        return button;
    }
    function displayDate(value) { return value ? value.split('-').reverse().join('/') : ''; }
    function render(payload, updateLegacy = false) {
        notes = payload.notas;
        if (updateLegacy) legacy.value = payload.nota_cobro ?? '';
        for (const type of ['GENERAL', 'CHECKLIST']) {
            const list = el(type === 'GENERAL' ? 'notas-generales-lista' : 'notas-tareas-lista');
            list.replaceChildren();
            const items = notes.filter(note => note.tipo === type);
            if (!items.length) list.append(node('p', type === 'GENERAL' ? 'Sin notas generales.' : 'Sin tareas.', 'py-3 text-sm text-slate-500'));
            items.forEach(note => {
                const item = node('article', '', 'space-y-2 py-3');
                const text = (type === 'CHECKLIST' ? (note.completada ? '☑ ' : '☐ ') : '') + note.texto;
                item.append(node('p', text, 'whitespace-pre-wrap break-words text-sm ' + (note.completada ? 'text-slate-500' : 'text-slate-800')));
                if (note.monto !== null) item.append(node('p', new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 2 }).format(note.monto), 'text-sm font-medium'));
                item.append(node('p', 'Creada: ' + note.created_at, 'text-xs text-slate-500'));
                if (note.fecha_objetivo) item.append(node('p', 'Para: ' + displayDate(note.fecha_objetivo), 'text-xs text-slate-500'));
                if (note.completada) item.append(node('p', 'Completada: ' + (note.completada_en ?? 'Fecha no disponible'), 'text-xs text-emerald-700'));
                const actions = node('div', '', 'flex flex-wrap gap-2');
                if (type === 'CHECKLIST') actions.append(action(note.completada ? 'Reabrir' : 'Marcar completada', () => mutate(baseUrl() + '/' + note.id + '/completada', 'PATCH', { completada: !note.completada })));
                actions.append(action('Editar', () => edit(type, note)));
                actions.append(action('Eliminar', () => {
                    if (window.confirm('¿Eliminar esta ' + (type === 'GENERAL' ? 'nota' : 'tarea') + '?')) mutate(baseUrl() + '/' + note.id, 'DELETE');
                }, true));
                item.append(actions);
                list.append(item);
            });
        }
        const hasNotes = notes.length > 0 || (payload.nota_cobro ?? '').trim().length > 0;
        buttons.filter(button => button.dataset.clienteId === String(clientId)).forEach(button => {
            button.dataset.nota = payload.nota_cobro ?? '';
            button.title = hasNotes ? 'Tiene notas registradas' : 'Sin notas';
            ['border-emerald-200', 'bg-emerald-50', 'hover:bg-emerald-100', 'text-emerald-700'].forEach(cls => button.classList.toggle(cls, hasNotes));
            ['border-slate-300', 'hover:bg-slate-100', 'text-slate-400'].forEach(cls => button.classList.toggle(cls, !hasNotes));
        });
        controls();
    }
    async function mutate(url, method, body) {
        if (busy || !ready) return;
        busy = true; controls(); message('Guardando…');
        try {
            const payload = await request(url, method, body);
            resetForm();
            render(payload);
            message('Cambios guardados.');
        } catch (error) { message(error.message, true); }
        finally { busy = false; controls(); }
    }
    buttons.forEach(button => button.addEventListener('click', async () => {
        if (busy) return;
        opener = button;
        clientId = button.dataset.clienteId;
        context = button.dataset.notasContexto;
        ready = false; busy = true; notes = []; resetForm();
        legacy.value = '';
        el('notas-generales-lista').replaceChildren();
        el('notas-tareas-lista').replaceChildren();
        el('nota-cobro-cliente').textContent = button.dataset.clienteNombre || 'Sin nombre';
        modal.classList.remove('hidden'); modal.classList.add('flex');
        controls(); message('Cargando notas…');
        try {
            const payload = await request(baseUrl());
            ready = true; render(payload, true); message('');
        } catch (error) { message(error.message, true); }
        finally { busy = false; controls(); el('nota-cobro-cancelar-top').focus(); }
    }));
    modal.querySelectorAll('[data-nueva-nota]').forEach(button => button.addEventListener('click', () => edit(button.dataset.nuevaNota)));
    form.addEventListener('submit', event => {
        event.preventDefault();
        const texto = el('cliente-nota-texto').value.trim();
        if (!texto) { message('El texto es obligatorio.', true); return; }
        mutate(baseUrl() + (editingId ? '/' + editingId : ''), editingId ? 'PATCH' : 'POST', {
            tipo: editingType, texto,
            monto: editingType === 'CHECKLIST' ? (el('cliente-nota-monto').value || null) : null,
            fecha_objetivo: editingType === 'CHECKLIST' ? (el('cliente-nota-fecha').value || null) : null,
        });
    });
    async function saveLegacy(clear) {
        if (!ready || busy) return;
        if (clear && !window.confirm('¿Limpiar únicamente la nota anterior?')) return;
        busy = true; controls(); message('Guardando nota anterior…');
        try {
            const payload = await request(urls.legacy.replace('__ID__', clientId), clear ? 'DELETE' : 'PATCH', clear ? undefined : { nota_cobro: legacy.value });
            render({ notas: notes, nota_cobro: payload.nota_cobro }, true);
            message(payload.message || 'Nota anterior actualizada.');
        } catch (error) { message(error.message, true); }
        finally { busy = false; controls(); }
    }
    el('nota-cobro-guardar').addEventListener('click', () => saveLegacy(false));
    el('nota-cobro-limpiar').addEventListener('click', () => saveLegacy(true));
    el('cliente-nota-form-cancelar').addEventListener('click', resetForm);
    ['nota-cobro-cancelar', 'nota-cobro-cancelar-top'].forEach(id => el(id).addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const focusable = [...modal.querySelectorAll('button:not(:disabled), input:not(:disabled), textarea:not(:disabled)')].filter(control => control.getClientRects().length);
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (!first) { event.preventDefault(); return; }
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
});
</script>
@endpush
