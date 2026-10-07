// Prueba del script real con DOM y fetch simulados. No abre la aplicación ni usa red.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
class Element {
    constructor(id = '', tag = 'div') {
        this.id = id; this.tag = tag; this.children = []; this.listeners = {};
        this.dataset = {}; this.value = ''; this.disabled = false; this.textContent = '';
        const classes = new Set();
        this.classList = {
            add: (...items) => items.forEach(item => classes.add(item)),
            remove: (...items) => items.forEach(item => classes.delete(item)),
            contains: item => classes.has(item),
            toggle: (item, value = !classes.has(item)) => value ? classes.add(item) : classes.delete(item),
        };
    }
    set innerHTML(_) { throw Error('El texto del usuario no debe insertarse como HTML'); }
    addEventListener(event, handler) { (this.listeners[event] ??= []).push(handler); }
    async fire(event, data = {}) {
        if (this.disabled && event === 'click') return;
        for (const handler of this.listeners[event] ?? []) await handler({ target: this, preventDefault() {}, ...data });
        await new Promise(resolve => setImmediate(resolve));
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute() {}
    focus() { document.activeElement = this; }
    getClientRects() { return [1]; }
    reset() { for (const id of ['cliente-nota-texto', 'cliente-nota-monto', 'cliente-nota-fecha']) elements[id].value = ''; }
    querySelectorAll(selector) {
        if (selector === '[data-nueva-nota]') return newButtons;
        const all = Object.values(elements).concat(newButtons);
        const walk = element => { for (const child of element.children) { all.push(child); walk(child); } };
        walk(elements['notas-generales-lista']); walk(elements['notas-tareas-lista']);
        return all.filter(element => ['button', 'input', 'textarea'].includes(element.tag));
    }
}
const html = fs.readFileSync(path.join(root, 'resources/views/partials/nota-cobro-modal.blade.php'), 'utf8');
const elements = {};
for (const match of html.matchAll(/<(\w+)[^>]*\bid="([^"]+)"[^>]*>/g)) elements[match[2]] = new Element(match[2], match[1]);
const newButtons = ['GENERAL', 'CHECKLIST'].map(tipo => {
    const element = new Element('', 'button'); element.dataset.nuevaNota = tipo; return element;
});
const openers = [new Element('one', 'button'), new Element('two', 'button')];
openers.forEach(element => { element.dataset = { clienteId: '7', clienteNombre: 'Cliente prueba', notasContexto: 'contexto-firmado' }; });
const document = {
    activeElement: null,
    getElementById: id => elements[id],
    querySelectorAll: () => openers,
    createElement: tag => new Element('', tag),
    addEventListener: (_event, handler) => handler(),
};
const requests = [], responses = [];
const payload = (notas = [], nota_cobro = 'Nota anterior intacta') => ({ notas, nota_cobro });
const task = { id: '12', tipo: 'CHECKLIST', texto: '<img src=x onerror=alert(1)>', monto: '50000.00', fecha_objetivo: '2026-11-01', created_at: '06/10/2026 08:00', completada: false, completada_en: null };
let source = fs.readFileSync(path.join(root, 'resources/views/partials/nota-cobro-script.blade.php'), 'utf8').split('<script>')[1].split('</script>')[0];
source = source.replace(/@json\(route\('clientes.notas.index'[^\n]+\)/, '"/clientes/__ID__/notas"')
    .replace(/@json\(route\('cobros.nota.update'[^\n]+\)/, '"/cobros/clientes/__ID__/nota"')
    .replace('@json(csrf_token())', '"csrf"');
vm.runInNewContext(source, {
    document, Intl, window: { confirm: () => true },
    fetch: async (url, options) => {
        requests.push({ url, ...options });
        assert.equal(options.headers['X-Notas-Contexto'], 'contexto-firmado');
        assert.ok(responses.length, 'Respuesta simulada no preparada');
        const result = responses.shift();
        return { ok: !result.status, status: result.status ?? 200, json: async () => result.body };
    },
});
async function run() {
    responses.push({ body: payload([task]) });
    await openers[0].fire('click');
    assert.equal(requests[0].method, 'GET');
    assert.equal(elements['nota-cobro-textarea'].value, 'Nota anterior intacta');
    assert.equal(elements['notas-tareas-lista'].children[0].children[0].textContent, '☐ ' + task.texto);
    assert.equal(openers[1].title, 'Tiene notas registradas');

    const actions = () => elements['notas-tareas-lista'].children[0].children.at(-1).children;
    responses.push({ body: payload([{ ...task, completada: true, completada_en: '06/10/2026 09:00' }]) });
    await actions()[0].fire('click');
    assert.equal(requests.at(-1).url, '/clientes/7/notas/12/completada');
    assert.equal(JSON.parse(requests.at(-1).body).completada, true);
    assert.equal(actions()[0].textContent, 'Reabrir');

    responses.push({ body: payload([task]) });
    await actions()[0].fire('click');
    assert.equal(JSON.parse(requests.at(-1).body).completada, false);

    await newButtons[0].fire('click');
    elements['cliente-nota-texto'].value = '   ';
    const count = requests.length;
    await elements['cliente-nota-form'].fire('submit');
    assert.equal(requests.length, count, 'No envía texto vacío');
    elements['cliente-nota-texto'].value = '  Nueva nota  ';
    responses.push({ body: payload([task, { ...task, id: '13', tipo: 'GENERAL', texto: 'Nueva nota', monto: null, fecha_objetivo: null }]) });
    await elements['cliente-nota-form'].fire('submit');
    assert.equal(requests.at(-1).method, 'POST');
    assert.deepEqual(JSON.parse(requests.at(-1).body), { tipo: 'GENERAL', texto: 'Nueva nota', monto: null, fecha_objetivo: null });

    responses.push({ body: payload([]) });
    await actions()[2].fire('click');
    assert.equal(requests.at(-1).method, 'DELETE');
    assert.equal(elements['nota-cobro-textarea'].value, 'Nota anterior intacta');

    responses.push({ body: { nota_cobro: null, message: 'Nota anterior limpiada' } });
    await elements['nota-cobro-limpiar'].fire('click');
    assert.equal(requests.at(-1).url, '/cobros/clientes/7/nota');
    assert.equal(openers[0].title, 'Sin notas');
    assert.equal(openers[1].title, 'Sin notas');

    await elements['nota-cobro-cancelar'].fire('click');
    responses.push({ status: 409, body: { message: 'Cliente ambiguo' } });
    await openers[0].fire('click');
    assert.equal(elements['nota-cobro-feedback'].textContent, 'Cliente ambiguo');
    assert.equal(elements['nota-cobro-guardar'].disabled, true);
    assert.equal(newButtons[0].disabled, true);
    assert.equal(elements['nota-cobro-cancelar'].disabled, false);
    assert.equal(responses.length, 0);
    console.log('OK: carga, texto seguro, completar/reabrir, crear, eliminar, nota anterior, iconos y bloqueo por ambigüedad (sin red).');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
