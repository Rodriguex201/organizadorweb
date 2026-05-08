@extends('layouts.admin')

@section('title', 'Detalle de Cobro')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-500">Módulo Cobros</p>
            <h1 class="text-2xl font-bold">Detalle de cobro #{{ $cobro->id_cobro }}</h1>
        </div>
        <a href="{{ route('cobros.index', request()->query()) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
            Volver al listado
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="bg-white rounded-lg shadow p-5 lg:col-span-2">
            <h2 class="text-lg font-semibold mb-4">Resumen del cobro</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">Mes</dt>
                    <dd class="font-medium">{{ ucfirst((string) ($cobro->mes ?? '')) ?: 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Año</dt>
                    <dd class="font-medium">{{ $cobro->año ?? 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">ID Cliente (ve.id_cliente)</dt>
                    <dd class="font-medium">{{ $cobro->id_cliente ?? 'N/D' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Estado Proforma</dt>
                    <dd>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) ($cobro->Proforma ?? 0) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ (int) ($cobro->Proforma ?? 0) === 1 ? 'Generada' : 'Pendiente' }}
                        </span>
                    </dd>
                </div>
            </dl>

            <div class="mt-6 border-t border-slate-200 pt-4">
                <h3 class="font-semibold mb-3">Campos numéricos y monetarios de valores_externos</h3>
                @php
                    $numericFields = collect(get_object_vars($cobro))
                        ->reject(fn ($value, $key) => str_starts_with($key, 'cliente_'))
                        ->filter(function ($value) {
                            if ($value === null || $value === '') {
                                return false;
                            }

                            return is_numeric($value);
                        });
                @endphp

                @if($numericFields->isEmpty())
                    <p class="text-sm text-slate-500">No se encontraron campos numéricos para este cobro.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                            <tr>
                                <th class="text-left px-3 py-2">Campo</th>
                                <th class="text-right px-3 py-2">Valor</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach($numericFields as $field => $value)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $field }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $value, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="bg-white rounded-lg shadow p-5">

            @php
                $nombre = trim((string) ($cobro->cliente_nombre ?? ''));
                $empresa = trim((string) ($cobro->cliente_empresa ?? ''));
                $contacto = trim((string) ($cobro->cliente_contacto ?? ''));
                $celular1 = trim((string) ($cobro->cliente_celular1 ?? ''));
                $celular2 = trim((string) ($cobro->cliente_celular2 ?? ''));

                $empresaMostrada = $empresa !== '' ? $empresa : ($nombre !== '' ? $nombre : null);
                $mostrarNombre = $nombre !== '' && ($empresa === '' || strcasecmp($nombre, $empresa) !== 0);
                $contactoMostrado = $contacto !== '' ? $contacto : null;
                $celularMostrado = $celular1 !== '' ? $celular1 : ($celular2 !== '' ? $celular2 : null);

                $camposOpcionales = [
                    'NIT' => trim((string) ($cobro->cliente_nit ?? '')),
                    'Código' => trim((string) ($cobro->cliente_codigo ?? '')),
                    'Email' => trim((string) ($cobro->cliente_email ?? '')),
                    'Dirección' => trim((string) ($cobro->cliente_direccion ?? '')),
                    'Régimen' => trim((string) ($cobro->cliente_regimen ?? '')),
                    'Modalidad' => trim((string) ($cobro->cliente_modalidad ?? '')),
                    'Categoría' => trim((string) ($cobro->cliente_categoria ?? '')),
                ];
            @endphp


            <h2 class="text-lg font-semibold mb-4">Datos del cliente</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">ID cliente potencial</dt>
                    <dd class="font-medium">{{ $cobro->cliente_id ?? 'N/D' }}</dd>
                </div>


                @if($empresaMostrada)
                    <div>
                        <dt class="text-slate-500">Empresa</dt>
                        <dd class="font-medium">{{ $empresaMostrada }}</dd>
                    </div>
                @endif

                @if($mostrarNombre)
                    <div>
                        <dt class="text-slate-500">Nombre</dt>
                        <dd>{{ $nombre }}</dd>
                    </div>
                @endif

                @if($contactoMostrado)
                    <div>
                        <dt class="text-slate-500">Contacto</dt>
                        <dd>{{ $contactoMostrado }}</dd>
                    </div>
                @endif

                @if($celularMostrado)
                    <div>
                        <dt class="text-slate-500">Celular</dt>
                        <dd>{{ $celularMostrado }}</dd>
                    </div>
                @endif

                @foreach($camposOpcionales as $label => $valor)
                    @if($valor !== '')
                        <div>
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd>{{ $valor }}</dd>
                        </div>
                    @endif
                @endforeach

                @if(!$empresaMostrada && !$mostrarNombre && !$contactoMostrado && !$celularMostrado && collect($camposOpcionales)->every(fn ($valor) => $valor === ''))
                    <div>
                        <dd class="text-slate-500">No hay información de cliente disponible para este registro.</dd>
                    </div>
                @endif

            </dl>

            <div class="mt-6 pt-4 border-t border-slate-200 space-y-2">
                <a href="{{ route('cobros.revisar', array_merge(['id' => $cobro->id_cobro], request()->query())) }}" class="inline-flex w-full items-center justify-center rounded bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Revisar proforma manualmente
                </a>
                <a href="{{ route('cobros.proforma.preview', array_merge(['id' => $cobro->id_cobro], request()->query())) }}" class="inline-flex w-full items-center justify-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Generar proforma (vista previa)
                </a>

                @if(!empty($proformaPersistidaId))
                    <a href="{{ route('proformas.pdf.show', $proformaPersistidaId) }}" target="_blank" class="inline-flex w-full items-center justify-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Ver PDF de proforma guardada
                    </a>
                    <form method="POST" action="{{ route('cobros.proforma.regenerar', $cobro->id_cobro) }}" onsubmit="return confirm('Esto reemplazará la proforma actual y actualizará sus valores. ¿Desea continuar?');">
                        @csrf
                        <input type="hidden" name="redirect_to" value="show">
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 1 1-9.588-3.368H3.5a.75.75 0 0 1 0-1.5h4a.75.75 0 0 1 .75.75v4a.75.75 0 0 1-1.5 0V9.18a4 4 0 1 0 6.98 2.45.75.75 0 0 1 1.482-.206Z" clip-rule="evenodd" />
                            </svg>
                            Regenerar proforma
                        </button>
                    </form>
                    <form method="POST" action="{{ route('proformas.enviar', $proformaPersistidaId) }}">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">
                            Enviar proforma por correo
                        </button>
                    </form>
                    <form method="POST" action="{{ route('proformas.enviar', $proformaPersistidaId) }}">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                            Reenviar proforma por correo
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
