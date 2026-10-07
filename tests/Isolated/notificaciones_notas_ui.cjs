// DOM, fetch y reloj simulados: no abre la aplicación ni usa red.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const elements = {}, events = {}, windowEvents = {}, requests = [], timers = new Map();
let serial = 0, ready;
class Element {
    constructor() {
        this.children = []; this.handlers = {}; this.disabled = false; this.attrs = {};
        const classes = new Set();
        this.classList = { add: c => classes.add(c), toggle: (c, value) => value ? classes.add(c) : classes.delete(c) };
    }
    set innerHTML(_) { throw Error('No insertar contenido de notas como HTML'); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    setAttribute(key, value) { this.attrs[key] = value; }
    addEventListener(event, fn) { this.handlers[event] = fn; }
    focus() {}
    contains(node) { return Object.values(elements).includes(node); }
    querySelectorAll() { return this.children.flatMap(c => c.children).filter(c => c.type === 'button'); }
    async click() { if (!this.disabled) await this.handlers.click?.({target:this}); await flush(); }
}
const html = fs.readFileSync(path.resolve(__dirname, '../../resources/views/partials/notificaciones-notas.blade.php'), 'utf8');
for (const m of html.matchAll(/id="([^"]+)"/g)) elements[m[1]] = new Element();
const get = suffix => elements['notificaciones-notas-' + suffix];
const document = { hidden: false, getElementById: id => elements[id], createElement: () => new Element(), addEventListener: (name, fn) => name === 'DOMContentLoaded' ? ready = fn : events[name] = fn };
const note = {id:'25', cliente:'Cliente de ejemplo', autor:'Usuario B', tipo:'GENERAL', resumen:'<img onerror=alert(1)>', fecha:'07/10/2026 10:00'};
let unread = true;
const source = html.split('<script>')[1].split('</script>')[0]
    .replace(/@json\(route\('notificaciones.notas.index'[^\n]+\)/, '"/notificaciones/notas"')
    .replace(/@json\(route\('notificaciones.notas.leida'[^\n]+\)/, '"/notificaciones/notas/__ID__/leida"')
    .replace('@json(csrf_token())', '"csrf"');
vm.runInNewContext(source, {
    document, window: {addEventListener:(name, fn) => windowEvents[name] = fn},
    clearTimeout: id => timers.delete(id), setTimeout: (fn, ms) => { assert.equal(ms, 30000); timers.set(++serial, fn); return serial; },
    fetch: async (url, options) => {
        requests.push({url, ...options});
        if (options.method === 'PATCH') { assert.equal(url, '/notificaciones/notas/25/leida'); unread = false; }
        return {ok:true, status:200, json:async () => ({no_leidas:unread ? 1 : 0, pagina:1, paginas:1, notificaciones:unread ? [note] : []})};
    },
});
function flush() { return new Promise(resolve => setImmediate(resolve)); }
async function run() {
    ready(); await flush();
    assert.equal(get('contador').textContent, '1');
    assert.equal(timers.size, 1);
    await get('abrir').click();
    assert.ok(requests.every(r => r.method === 'GET'), 'Abrir nunca marca lectura');
    assert.equal(get('lista').children[0].children[2].textContent, note.resumen);
    document.hidden = true; events.visibilitychange();
    assert.equal(timers.size, 0, 'Sin polling oculto');
    const before = requests.length;
    await flush(); assert.equal(requests.length, before);
    document.hidden = false; events.visibilitychange(); await flush();
    assert.equal(requests.length, before + 1, 'Actualiza al volver');
    await get('lista').children[0].children.at(-1).click();
    assert.equal(requests.filter(r => r.method === 'PATCH').length, 1);
    assert.equal(get('contador').textContent, '0');
    assert.equal(get('lista').children.length, 0);
    assert.equal(get('anterior').disabled, true);
    assert.equal(get('siguiente').disabled, true);
    windowEvents.pagehide(); assert.equal(timers.size, 0);
    console.log('OK: campana, apertura sin escritura, texto seguro, lectura explícita, contador y polling visible; sin red.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
