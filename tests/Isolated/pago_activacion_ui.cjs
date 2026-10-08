// Ejecuta el JS real de la vista con DOM/fetch simulados, sin Laravel, red ni BD.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/proformas/index.blade.php'), 'utf8');
function section(start, end) {
    const a = view.indexOf(start);
    const b = view.indexOf(end, a);
    assert.ok(a >= 0 && b > a, 'Bloque real de la vista localizado');
    return view.slice(a, b);
}
class Element {
    constructor() {
        this.handlers = {};
        this.classes = new Set(['hidden']);
        this.classList = {add: c => this.classes.add(c), remove: c => this.classes.delete(c)};
    }
    addEventListener(name, callback) { this.handlers[name] = callback; }
    focus() { document.activeElement = this; }
}
const elements = Object.fromEntries(['pago-activacion-modal', 'pago-activacion-no', 'pago-activacion-si'].map(id => [id, new Element()]));
const document = {getElementById: id => elements[id], activeElement: null};
const row = {dataset: {updateUrl:'/pago/42', activacionShowUrl:'/activacion/42', activacionUpdateUrl:'/guardar/42', activacionEventosUpdateUrl:'/eventos/42', codigo:'B42', proformaId:'42', nit:'123', clienteId:'99'}, querySelector: () => new Element()};
const expectedContext = {showUrl:'/activacion/42', updateUrl:'/guardar/42', eventosUpdateUrl:'/eventos/42', codigo:'B42', proforma:'42', nit:'123', clienteId:'99'};
const activationCalls = [], requests = [];
let reply;
const context = vm.createContext({
    document, ESTADO_PAGADA:4, csrfToken:'test', console:{error(){}},
    FormData: class {append(){}},
    loadActivationData: async data => activationCalls.push(JSON.parse(JSON.stringify(data))),
    fetch: async (url, options) => { requests.push({url, method:options.method}); return {ok:reply.ok, json:async()=>reply}; },
    updateRowState: (r, state) => { r.dataset.estado = state; }, showFeedback(){},
    pendingPaymentRow:row, paymentSubmitting:false,
    paymentForm:new Element(), paymentMethod:{value:'EFECTIVO'}, paymentConfirmButton:new Element(),
    paymentReceipt:{files:[]}, paymentFeedback:new Element(),
});
context.closePaymentModal = () => { context.pendingPaymentRow = null; };
vm.runInContext(section('        const activationContextForRow =', '        tableRows.forEach((row) => {'), context);
vm.runInContext(section("        paymentForm?.addEventListener('submit'", '        const syncPaymentReceiptRequirement ='), context);
const modal = elements['pago-activacion-modal'];
const no = elements['pago-activacion-no'];
const yes = elements['pago-activacion-si'];
const submit = async payload => {
    reply = payload;
    context.pendingPaymentRow = row;
    await context.paymentForm.handlers.submit({preventDefault(){}});
};
(async () => {
    await submit({ok:true, from:2, to:4});
    assert.equal(modal.classes.has('hidden'), false, 'Pago nuevo ofrece activación');
    assert.equal(context.pendingPaymentRow, null, 'El modal de pago limpió la fila pendiente');
    assert.equal(activationCalls.length, 0, 'No activa ni consulta automáticamente');
    no.handlers.click();
    assert.equal(modal.classes.has('hidden'), true, 'No cierra confirmación');
    assert.equal(activationCalls.length, 0, 'No no realiza otra acción');

    await submit({ok:true, from:'2', to:'4'});
    await yes.handlers.click();
    assert.equal(modal.classes.has('hidden'), true);
    assert.deepEqual(activationCalls, [expectedContext], 'Sí reutiliza contexto exacto aunque pendingPaymentRow sea null');
    await yes.handlers.click();
    assert.equal(activationCalls.length, 1, 'No reutiliza contexto ya consumido');

    await submit({ok:true, from:4, to:4});
    assert.equal(modal.classes.has('hidden'), true, 'Reemplazo no ofrece activación');
    await submit({ok:false, from:2, to:4});
    assert.equal(modal.classes.has('hidden'), true, 'Error de pago no ofrece activación');
    assert.equal(context.pendingPaymentRow, row, 'Error conserva formulario de pago');
    await submit({ok:true, from:2, to:5});
    assert.equal(modal.classes.has('hidden'), true, 'Otro estado no ofrece activación');
    assert.ok(requests.every(r => r.url === '/pago/42' && r.method === 'POST'), 'Solo peticiones de pago; ninguna escritura de activación');
    console.log('OK: pago nuevo, reemplazo, No, Sí, contexto conservado, doble clic y errores; sin BD/red.');
})().catch(error => { console.error(error); process.exitCode = 1; });
