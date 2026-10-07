<div id="whatsapp-modal" role="dialog" aria-modal="true" aria-labelledby="whatsapp-titulo" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
        <div class="flex items-center justify-between gap-4">
            <h2 id="whatsapp-titulo" class="text-lg font-semibold">Proformas por WhatsApp</h2>
            <button id="whatsapp-cerrar" type="button" aria-label="Cerrar destinatarios WhatsApp" class="rounded px-3 py-2">Cerrar</button>
        </div>
        <p class="mt-1 text-sm text-slate-600">Configura los destinatarios y su grupo. Agregar un cliente no envía proformas.</p>
        <div class="my-4 flex gap-2" aria-label="Filtrar destinatarios por grupo">
            @foreach(['7' => 'Grupo 7', '27' => 'Grupo 27', '' => 'Todos'] as $valor => $label)
                <button type="button" data-whatsapp-grupo="{{ $valor }}" aria-pressed="{{ $valor === '' ? 'true' : 'false' }}" class="rounded border border-emerald-300 px-3 py-2 text-sm aria-pressed:bg-emerald-100">{{ $label }}</button>
            @endforeach
        </div>
        <p id="whatsapp-estado" role="status" aria-live="polite" class="my-2 text-sm text-slate-700"></p>
        <form id="whatsapp-busqueda" class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1"><label for="whatsapp-buscar" class="block text-sm font-medium">Buscar cliente</label>
                <input id="whatsapp-buscar" type="search" minlength="2" maxlength="100" placeholder="Código, nombre o empresa" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-white">Buscar</button>
        </form>
        <div id="whatsapp-resultados" class="my-3 space-y-2" aria-live="polite"></div>
        <form id="whatsapp-form" class="my-4 hidden space-y-3 rounded border border-emerald-200 bg-emerald-50 p-4">
            <h3 id="whatsapp-editor-titulo" class="font-semibold"></h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label for="whatsapp-grupo" class="block text-sm">Grupo</label><select id="whatsapp-grupo" class="w-full rounded border p-2"><option value="7">Grupo 7</option><option value="27">Grupo 27</option></select></div>
                <div><label for="whatsapp-fuente" class="block text-sm">Número de WhatsApp</label><select id="whatsapp-fuente" class="w-full rounded border p-2"><option value="CELULAR1">Celular 1</option><option value="CELULAR2">Celular 2</option><option value="ALTERNATIVO">Número alternativo</option></select></div>
            </div>
            <p id="whatsapp-telefonos" class="text-sm text-slate-600"></p>
            <div id="whatsapp-alternativo-campo" class="hidden"><label for="whatsapp-alternativo" class="block text-sm">Número alternativo con prefijo internacional</label><input id="whatsapp-alternativo" type="tel" maxlength="30" placeholder="+573001234567" class="w-full rounded border p-2"></div>
            <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-white">Guardar</button>
            <button id="whatsapp-cancelar" type="button" class="rounded bg-white px-4 py-2">Cancelar</button>
        </form>
        <h3 class="mt-4 font-semibold">Clientes configurados</h3>
        <div class="mt-2 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-100"><tr>
            <th class="p-2">Código</th><th class="p-2">Empresa</th><th class="p-2">WhatsApp</th><th class="p-2">Grupo</th><th class="p-2">Estado</th><th class="p-2">Acciones</th>
        </tr></thead><tbody id="whatsapp-lista"></tbody></table></div>
        <div class="mt-3 flex items-center justify-between gap-2 text-sm"><button id="whatsapp-anterior" type="button" class="rounded border px-3 py-2 disabled:opacity-40">Anterior</button><span id="whatsapp-pagina"></span><button id="whatsapp-siguiente" type="button" class="rounded border px-3 py-2 disabled:opacity-40">Siguiente</button></div>
    </div>
</div>
