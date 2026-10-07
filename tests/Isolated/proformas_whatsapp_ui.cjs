// DOM y fetch simulados: sin navegador, red ni base de datos.
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict'), path = require('node:path');
const read = file => fs.readFileSync(path.resolve(__dirname, '../../resources/views/partials/' + file), 'utf8');
const elements = {}, requests = []; let ready, rows = [], search = [], fail = false;
class Element {
    constructor() {
        this.children = []; this.handlers = {}; this.value = ''; this.dataset = {}; this.attrs = {};
        const classes = new Set();
        this.classList = {add:c=>classes.add(c), remove:c=>classes.delete(c), toggle:(c,on)=>on?classes.add(c):classes.delete(c)};
    }
    set innerHTML(_) { throw Error('Contenido dinámico inseguro'); }
    append(...items) { this.children.push(...items); }
    replaceChildren() { this.children = []; }
    addEventListener(name, fn) { this.handlers[name] = fn; }
    setAttribute(name, value) { this.attrs[name] = value; }
    focus() {}
    querySelectorAll(selector) { return selector === '[data-whatsapp-grupo]' ? filters : Object.values(elements); }
    async trigger(name) { this.handlers[name]?.({preventDefault(){}, target:this}); await flush(); }
}
for (const match of read('proformas-whatsapp-modal.blade.php').matchAll(/id="([^"]+)"/g)) elements[match[1]] = new Element();
elements['whatsapp-abrir'] = new Element();
const get = id => elements['whatsapp-' + id];
const filters = ['7','27',''].map(group => { const e = new Element(); e.dataset.whatsappGrupo = group; return e; });
const client = {cliente_id:556, codigo:'B543', empresa:'<script>cliente</script>', nit:'123', celular1:'3001234567', celular2:'', configurado:false};
const configured = {...client, grupo_fecha:7, telefono_fuente:'CELULAR1', whatsapp_alternativo:null, whatsapp:'+573001234567', telefono_valido:true, activo:true};
let source = read('proformas-whatsapp-script.blade.php').split('<script>')[1].split('</script>')[0];
source = source.replace(/@json\(route\('proformas\.whatsapp\.(\w+)'[^\n]*\)\)/g, (_, name) => JSON.stringify('/' + name + (['store','update','estado'].includes(name)?'/__ID__':''))).replace('@json(csrf_token())', '"csrf"');
vm.runInNewContext(source, {
    document:{getElementById:id=>elements[id],createElement:()=>new Element(),addEventListener:(name,fn)=>{ready=fn;}},
    fetch:async (url, options) => {
        requests.push({url,...options});
        if (fail) return {ok:false,status:409,json:async()=>({message:'Ya agregado'})};
        let payload = url.startsWith('/buscar') ? {data:search} : {data:rows,pagina:1,paginas:1,total:rows.length};
        if (options.method === 'POST') { rows = [configured]; search = [{...client,configurado:true,activo:true}]; }
        if (url.startsWith('/estado')) rows = [{...configured,activo:JSON.parse(options.body).activo}];
        return {ok:true,status:200,json:async()=>payload};
    },
});
function flush() { return new Promise(resolve=>setImmediate(resolve)); }
async function run() {
    ready(); await get('abrir').trigger('click');
    assert.equal(requests.length, 1); assert.equal(requests[0].method, 'GET');
    search = [client]; get('buscar').value = 'B543'; await get('busqueda').trigger('submit');
    assert.equal(get('resultados').children[0].children[0].textContent.includes(client.empresa), true);
    await get('resultados').children[0].children[1].trigger('click');
    get('grupo').value = '7'; get('fuente').value = 'CELULAR1';
    await get('form').trigger('submit');
    assert.equal(requests.filter(r=>r.method==='POST').length, 1);
    assert.equal(get('lista').children.length, 1);
    assert.equal(get('resultados').children[0].children[1].textContent, 'Ya agregado');
    await get('lista').children[0].children[5].children[0].trigger('click');
    get('grupo').value = '27'; get('fuente').value = 'ALTERNATIVO'; get('alternativo').value = '+14155550123';
    await get('form').trigger('submit');
    const edit = requests.find(r=>r.url==='/update/556');
    assert.equal(JSON.parse(edit.body).grupo_fecha, 27);
    assert.equal(JSON.parse(edit.body).whatsapp_alternativo, '+14155550123');
    await get('lista').children[0].children[5].children[1].trigger('click');
    assert.equal(get('lista').children[0].children[4].textContent, 'Inactivo');
    await get('lista').children[0].children[5].children[1].trigger('click');
    assert.equal(get('lista').children[0].children[4].textContent, 'Activo');
    await filters[1].trigger('click'); assert.ok(requests.some(r=>r.url==='/index?grupo=27&pagina=1'));
    await filters[2].trigger('click'); assert.ok(requests.some(r=>r.url==='/index?grupo=&pagina=1'));
    fail = true; await get('busqueda').trigger('submit');
    assert.equal(get('estado').textContent, 'Ya agregado');
    assert.equal(get('buscar').disabled, false);
    console.log('OK: alta, duplicados UI, edición, estado, filtros, errores y texto seguro; sin red.');
}
run().catch(error=>{console.error(error);process.exitCode=1;});
