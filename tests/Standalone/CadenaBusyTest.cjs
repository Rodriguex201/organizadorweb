// Execute the actual inline UI code with deferred HTTP responses; no server/DB.
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const code = fs.readFileSync('resources/views/importaciones/cadena/index.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const cats = ['facturas', 'soporte', 'eventos'], els = {};
let buttons, pickers, calls = 0, settle, rejectRequest, downloaded = 0;
class E {
    constructor() { this.listeners = {}; this.files = []; this.children = []; this.value = ''; this.disabled = false; this.textContent = ''; this.attrs = {}; }
    addEventListener(k, f) { (this.listeners[k] ??= []).push(f); }
    async fire(k, e = {}) { for (const f of this.listeners[k] ?? []) await f(e); }
    append(...x) { this.children.push(...x); }
    replaceChildren(...x) { this.children = x; }
    setAttribute(k, v) { this.attrs[k] = v; }
    removeAttribute(k) { delete this.attrs[k]; }
    focus() {}
    click() { downloaded++; }
    querySelectorAll(s) {
        if (s === '[data-cadena-generate]') return buttons;
        if (s === '[data-cadena-picker]') return pickers;
        if (s === 'a') return [clearLink];
        if (s === 'input[type=file]') return cats.map(c => els['cadena-' + c]);
        return [...buttons, ...pickers, ...cats.map(c => els['cadena-' + c])];
    }
}
const clearLink = new E();
for (const id of ['form','mes','anio','periodo','invalidada','result','result-message','result-issues','result-download', ...cats.flatMap(c => [c, ...['count','list','error','add','clear'].map(s => c+'-'+s)])]) els['cadena-'+id] = new E();
buttons = [...cats,'paquete'].map(c => Object.assign(new E(), {dataset: {cadenaGenerate: c}, textContent: c}));
pickers = cats.map(c => Object.assign(new E(), {dataset: {cadenaPicker: c}}));
class DT { constructor() { this.files = []; this.items = {add: f => this.files.push(f)}; } }
class FD extends Map { constructor(form) { super(); for (const f of form.listeners.formdata ?? []) f({formData: this}); } }
vm.runInNewContext(code, {document: {getElementById: id => els[id] ?? null, createElement: () => new E(), createTextNode: text => ({textContent: text})}, window: new E(), DataTransfer: DT, FormData: FD,
    URL: {revokeObjectURL() {}, createObjectURL() { return 'blob:test'; }},
    fetch: () => { calls++; return new Promise((resolve, reject) => { settle = resolve; rejectRequest = reject; }); }});
const submit = i => els['cadena-form'].fire('submit', {submitter: buttons[i], preventDefault() {}});
const reply = status => ({ok: status === 200, status, headers: {get: k => k === 'X-Cadena-Filename' && status === 200 ? 'Resumen.xlsx' : 'application/json'}, json: async () => ({message: 'Error de prueba', errors: {mes: ['Revisa el período']}}), blob: async () => ({})});
(async () => {
    for (const c of cats) { els['cadena-'+c].files = [{name: c, size: 1}]; await els['cadena-'+c].fire('change'); }
    for (const [i, status] of [200,422,409,500,'network'].entries()) {
        const index = i % 4, before = calls, priorDownloads = downloaded;
        const running = submit(index);
        assert.ok(buttons.every(b => b.disabled));
        assert.equal(buttons[index].children[1].textContent, 'Procesando...');
        assert.equal(els['cadena-form'].attrs['aria-busy'], 'true');
        assert.ok(pickers.every(b => b.disabled));
        let prevented = false; await clearLink.fire('click', {preventDefault() { prevented = true; }}); assert.ok(prevented);
        await submit(index); await submit((index + 1) % 4); assert.equal(calls, before + 1);
        if (status === 'network') rejectRequest(new Error('network')); else settle(reply(status));
        await running;
        assert.ok(buttons.every(b => !b.disabled)); assert.ok(pickers.every(b => !b.disabled));
        assert.equal(buttons[index].textContent, [...cats,'paquete'][index]);
        assert.equal(els['cadena-form'].attrs['aria-busy'], undefined);
        assert.ok(cats.every(c => els['cadena-'+c].files.length === 1));
        assert.equal(downloaded, priorDownloads + (status === 200 ? 1 : 0));
    }
    console.log('OK: doble clic, otro botón, éxito, 422, 409, 500, red y recuperación de controles/selección.');
})().catch(e => { console.error(e); process.exitCode = 1; });
