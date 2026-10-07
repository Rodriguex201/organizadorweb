<div id="nota-cobro-modal" role="dialog" aria-modal="true" aria-labelledby="nota-cobro-titulo" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="nota-cobro-titulo" class="text-base font-semibold">Notas del cliente — <span id="nota-cobro-cliente"></span></h2>
            <button id="nota-cobro-cancelar-top" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">×</button>
        </div>
        <p id="nota-cobro-feedback" role="status" aria-live="polite" class="hidden px-5 py-3 text-sm"></p>
        <div class="space-y-6 overflow-y-auto px-5 py-4">
            <section aria-labelledby="notas-generales-titulo">
                <h3 id="notas-generales-titulo" class="border-b pb-2 text-sm font-semibold uppercase text-slate-600">Notas generales</h3>
                <div id="notas-generales-lista" class="divide-y divide-slate-100"></div>
                <button type="button" data-nueva-nota="GENERAL" data-nota-write class="mt-3 rounded bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700">+ Nueva nota</button>
            </section>
            <section aria-labelledby="notas-tareas-titulo">
                <h3 id="notas-tareas-titulo" class="border-b pb-2 text-sm font-semibold uppercase text-slate-600">Tareas / checklist</h3>
                <div id="notas-tareas-lista" class="divide-y divide-slate-100"></div>
                <button type="button" data-nueva-nota="CHECKLIST" data-nota-write class="mt-3 rounded bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700">+ Nueva tarea</button>
            </section>
            <form id="cliente-nota-form" class="hidden space-y-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <h3 id="cliente-nota-form-titulo" class="font-semibold"></h3>
                <label class="block text-sm">Texto <span aria-hidden="true">*</span>
                    <textarea id="cliente-nota-texto" required maxlength="10000" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                </label>
                <div id="cliente-nota-tarea-campos" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block text-sm">Monto (opcional)
                        <input id="cliente-nota-monto" type="number" min="0" max="9999999999999.99" step="0.01" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">Fecha objetivo (opcional)
                        <input id="cliente-nota-fecha" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button id="cliente-nota-form-cancelar" type="button" class="rounded bg-white px-3 py-2 text-sm">Cancelar edición</button>
                    <button data-nota-write type="submit" class="rounded bg-indigo-600 px-3 py-2 text-sm text-white">Guardar</button>
                </div>
            </form>
            <section aria-labelledby="nota-anterior-titulo" class="border-t pt-4">
                <h3 id="nota-anterior-titulo" class="text-sm font-semibold uppercase text-slate-600">Nota anterior</h3>
                <p class="my-2 text-xs text-slate-500">Fecha original no disponible</p>
                <label for="nota-cobro-textarea" class="sr-only">Texto de la nota anterior</label>
                <textarea id="nota-cobro-textarea" rows="3" maxlength="2000" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Sin nota anterior"></textarea>
                <div class="mt-2 flex justify-end gap-2">
                    <button id="nota-cobro-limpiar" data-nota-write type="button" class="rounded bg-rose-100 px-3 py-2 text-sm font-medium text-rose-700">Limpiar</button>
                    <button id="nota-cobro-guardar" data-nota-write type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Guardar</button>
                </div>
            </section>
        </div>
        <div class="flex justify-end border-t px-5 py-3">
            <button id="nota-cobro-cancelar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700">Cerrar</button>
        </div>
    </div>
</div>
