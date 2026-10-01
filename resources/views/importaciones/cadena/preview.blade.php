<section class="space-y-5">
    <h2 class="text-xl font-bold">Vista previa — Período seleccionado: {{ ucfirst($selectedMes) }} {{ $selectedAnio }}</h2>
    <p class="text-sm text-slate-600">Cantidades observadas en esta carga, sujetas a los errores y límites de deduplicación indicados. Un NIT pendiente no está asignado a ningún cliente. Los vacíos se muestran como CONSERVAR; un 0 solo aparece si el archivo lo informa expresamente.</p>
    @isset($preview['descargas'])
        <div class="rounded bg-white p-4 shadow space-y-3">
            <h3 class="font-semibold">Descargar resúmenes para Importaciones</h3>
            <p class="text-sm">Período de los archivos: {{ ucfirst($selectedMes) }} {{ $selectedAnio }}. Descarga vigente durante 30 minutos. No se aplica ningún dato.</p>
            <p class="text-sm text-amber-800">Las descargas usan las columnas históricas, sin cadena_alcance_v1. Al subirlas a Importaciones se aplica el comportamiento histórico: los vacíos se interpretan como cero; el CONSERVAR de esta vista previa no se transmite en esos archivos. Los NIT ambiguos o sin cliente se incluyen sin asignación automática, también en soporte, y deben resolverse en Importaciones. Descargar no aplica datos.</p>
            <p class="text-sm">Eventos conserva las etiquetas heredadas: Acuse = 032 (facturable), Recibo = 030, Aceptación expresa = 033, Aceptación tácita = 034, Reclamo = 031. Los archivos de auditoría son para revisión, no para importar.</p>
            @if(in_array('ResumenCombinado.xlsx', $preview['descargas']['archivos'], true))
                <p class="text-sm text-amber-800">Para facturas/notas utiliza ResumenCombinado.xlsx. Resumen.xlsx es una copia de compatibilidad con las mismas cantidades: no importes ambos.</p>
            @endif
            <div class="flex flex-wrap gap-3">
                @if(($preview['descargas']['paquete_errores'] ?? ['Prepara una nueva vista previa.']) === [])
                    <form method="POST" action="{{ route('configuracion.importaciones.cadena.package') }}">
                        @csrf
                        <input type="hidden" name="mes" value="{{ $selectedMes }}">
                        <input type="hidden" name="anio" value="{{ $selectedAnio }}">
                        @foreach(\App\Services\Cadena\CadenaPaqueteService::FILES as $packageFilename => $packageCategory)
                            <input type="hidden" name="tokens[{{ $packageFilename }}]" value="{{ $preview['descargas']['tokens'][$packageFilename] }}">
                        @endforeach
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white">Descargar todo en ZIP</button>
                        <p class="text-sm">Incluye los tres resúmenes comerciales. Auditorías disponibles por separado.</p>
                    </form>
                @else
                    <details class="text-sm"><summary>Paquete completo no disponible</summary>
                        @foreach($preview['descargas']['paquete_errores'] ?? ['Prepara una nueva vista previa.'] as $packageError)
                            <p class="text-amber-800">{{ $packageError }}</p>
                        @endforeach
                    </details>
                @endif
                @foreach($preview['descargas']['tokens'] as $filename => $downloadToken)
                @if($filename === 'Resumen.xlsx')
                <details class="text-sm"><summary class="cursor-pointer underline">Opción secundaria de compatibilidad</summary>
                @endif
                <form method="POST" action="{{ route('configuracion.importaciones.cadena.download') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $downloadToken }}">
                <input type="hidden" name="mes" value="{{ $selectedMes }}">
                <input type="hidden" name="anio" value="{{ $selectedAnio }}">
                <input type="hidden" name="archivo" value="{{ $filename }}">
                    <button type="submit" name="archivo" value="{{ $filename }}" class="rounded bg-indigo-600 px-4 py-2 text-white">{{ $filename === 'ResumenCombinado.xlsx' ? 'Descargar resumen combinado' : $filename }}</button>
                </form>
                @if($filename === 'Resumen.xlsx')</details>@endif
                @endforeach
            </div>
            <p class="text-sm">Las auditorías grandes se dividen en partes de hasta 40.000 filas. Descarga todas las partes para conservar la auditoría completa.</p>
            @foreach($preview['descargas']['errores'] as $downloadError)
                <p class="text-sm text-amber-800" role="alert">{{ $downloadError }}</p>
            @endforeach
            @foreach($preview['descargas']['omitidos'] as $omitted)
                <p class="text-sm text-amber-800">{{ implode(' · ', $omitted) }}</p>
            @endforeach
        </div>
    @endisset
    @foreach ($preview['advertencias'] as $warning)
        <p class="rounded bg-amber-50 p-3 text-sm text-amber-900">{{ $warning }}</p>
    @endforeach
    <div class="grid gap-3 md:grid-cols-3">
        @foreach ($preview['alcance'] as $category => $scope)
            <div class="rounded border bg-white p-3 text-sm"><strong>{{ ucfirst($category) }}</strong><p>{{ $scope }}</p></div>
        @endforeach
    </div>
    <div class="overflow-x-auto rounded bg-white p-4 shadow">
        <h3 class="mb-3 font-semibold">Archivos y hash SHA-256</h3>
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-2">Archivo / categoría</th><th class="p-2">Resultado</th><th class="p-2">Hash</th></tr></thead>
            <tbody>@foreach ($preview['archivos'] as $file)
                <tr class="border-t"><td class="p-2">{{ $file['archivo'] }}<br>{{ $file['categoria'] }}</td><td class="p-2">{{ $file['estado'] }}<br>{{ $file['detalle'] }}</td><td class="break-all p-2 font-mono text-xs">{{ $file['hash'] }}</td></tr>
            @endforeach</tbody>
        </table>
    </div>
    @forelse ($preview['clientes'] as $client)
        <article class="space-y-3 rounded border bg-white p-4 shadow">
            <h3 class="font-semibold">{{ $client['nit_base'] }}-{{ $client['dv'] }} — {{ $client['cliente'] }}</h3>
            <p class="text-sm">{{ $client['estado'] }} · cliente_id: {{ $client['cliente_id'] ?? 'Pendiente' }} · DV {{ $client['dv_informado'] ? 'informado' : 'calculado' }}{{ $client['dv_valido'] ? '' : ' — no coincide con el calculado' }}</p>
            @if ($client['cliente_id'] === null)
                <p class="text-sm text-amber-800">Pendiente de asignación. No se crean clientes automáticamente.</p>
                @foreach ($client['candidatos'] as $candidate)
                    <p class="text-sm">Candidato #{{ $candidate['cliente_id'] }} · {{ $candidate['codigo'] ?? '' }} · {{ $candidate['nombre'] }} · DV {{ $candidate['dv'] }}</p>
                @endforeach
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-2">Código</th><th class="p-2">Concepto</th><th class="p-2">Cantidad observada</th></tr></thead>
                    <tbody>
                    @foreach (\App\Services\Cadena\CadenaValidacionService::DOCUMENTOS + \App\Services\Cadena\CadenaValidacionService::EVENTOS as $code => $label)
                        <tr class="border-t"><td class="p-2">{{ $code }}</td><td class="p-2">{{ $label }}</td><td class="p-2">{{ $client['cantidades'][$code] ?? 'CONSERVAR' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm font-semibold">Cantidad de eventos facturables — código 032: {{ $client['cantidades']['032'] ?? 'CONSERVAR' }}</p>
            <p class="text-xs text-slate-600">Documento soporte: número de documentos 05 × precio soporte. Las notas 95 se cuentan por separado y no generan un cargo nuevo.</p>
            <div class="rounded bg-slate-50 p-3 text-sm">
                <strong>Destino y proformas del período</strong>
                @forelse ($client['destinos'] as $destination)
                    <p class="mt-2">Cobro #{{ $destination['id_cobro'] }} · {{ implode(', ', $destination['protecciones']) ?: 'Sin estado protegido en el cobro' }}</p>
                    @forelse ($destination['proformas'] as $proforma)
                        <p>Proforma #{{ $proforma['id'] }} · {{ implode(', ', $proforma['protecciones']) ?: 'Sin estado protegido' }}{{ $proforma['legacy'] ? ' · Posible coincidencia histórica por NIT/emisora, sin vincular' : '' }}</p>
                    @empty
                        <p>No se encontraron proformas asociadas.</p>
                    @endforelse
                @empty
                    <p>{{ $client['cliente_id'] === null ? 'No evaluado: cliente pendiente de asignación.' : 'No existe cobro destino para este cliente en el período.' }}</p>
                @endforelse
                @if (count($client['destinos']) > 1)<p class="text-amber-800">Múltiples cobros destino: requieren revisión.</p>@endif
            </div>
        </article>
    @empty
        <p class="rounded bg-white p-4">No hay filas válidas para resumir. Revisa el diagnóstico de los archivos.</p>
    @endforelse
    <details class="rounded bg-white p-4 shadow" open>
        <summary class="cursor-pointer font-semibold">Auditoría de filas: conteos, exclusiones, duplicados y errores ({{ count($preview['auditoria']) }})</summary>
        <div class="mt-3 max-h-96 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-2">Archivo / hoja / fila</th><th class="p-2">Resultado</th><th class="p-2">Detalle</th></tr></thead>
                <tbody>@foreach ($preview['auditoria'] as $entry)
                    <tr class="border-t"><td class="p-2">{{ $entry['archivo'] }} / {{ $entry['hoja'] }} / {{ $entry['fila'] }}</td><td class="p-2">{{ $entry['estado'] }}</td><td class="p-2">{{ $entry['detalle'] }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
    </details>
</section>
