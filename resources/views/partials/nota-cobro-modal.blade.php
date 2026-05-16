<div id="nota-cobro-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-base font-semibold">Nota de cobro</h2>
            <button id="nota-cobro-cancelar-top" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">×</button>
        </div>
        <div class="space-y-3 px-4 py-4">
            <p id="nota-cobro-cliente" class="text-sm text-slate-600"></p>
            <textarea
                id="nota-cobro-textarea"
                rows="6"
                maxlength="2000"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Escribe una nota de cobro para este cliente..."
            ></textarea>
            <p id="nota-cobro-feedback" class="hidden text-sm"></p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <button id="nota-cobro-limpiar" type="button" class="rounded bg-rose-100 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200">Limpiar</button>
            <button id="nota-cobro-cancelar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
            <button id="nota-cobro-guardar" type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar</button>
        </div>
    </div>
</div>
