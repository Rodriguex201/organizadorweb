<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientesController extends Controller
{
    public function index(Request $request): View
    {
        $mapping = $this->resolveColumnMapping();

        $query = DB::table('clientes_potenciales');

        $selects = [
            $mapping['id'] ? "{$mapping['id']} as id" : DB::raw('NULL as id'),
            $mapping['nit'] ? "{$mapping['nit']} as nit" : DB::raw('NULL as nit'),
            $mapping['dv'] ? "{$mapping['dv']} as dv" : DB::raw('NULL as dv'),
            $mapping['nombre'] ? "{$mapping['nombre']} as nombre" : DB::raw('NULL as nombre'),
            $mapping['codigo'] ? "{$mapping['codigo']} as codigo" : DB::raw('NULL as codigo'),
            $mapping['empresa'] ? "{$mapping['empresa']} as empresa" : DB::raw('NULL as empresa'),
            $mapping['correo'] ? "{$mapping['correo']} as correo" : DB::raw('NULL as correo'),
            $mapping['telefono'] ? "{$mapping['telefono']} as telefono" : DB::raw('NULL as telefono'),
            $mapping['contacto'] ? "{$mapping['contacto']} as contacto" : DB::raw('NULL as contacto'),
            $mapping['fecha_inicio'] ? "{$mapping['fecha_inicio']} as fecha_inicio" : DB::raw('NULL as fecha_inicio'),
            $mapping['fecha_arriendo'] ? "{$mapping['fecha_arriendo']} as fecha_arriendo" : DB::raw('NULL as fecha_arriendo'),
            $mapping['fecha_cotizacion'] ? "{$mapping['fecha_cotizacion']} as fecha_cotizacion" : DB::raw('NULL as fecha_cotizacion'),
            $mapping['fecha_retiro'] ? "{$mapping['fecha_retiro']} as fecha_retiro" : DB::raw('NULL as fecha_retiro'),
            $mapping['contrato'] ? "{$mapping['contrato']} as contrato" : DB::raw('NULL as contrato'),
        ];

        $query->select($selects);

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $searchColumns = array_filter([
                $mapping['nombre'],
                $mapping['empresa'],
                $mapping['nit'],
                $mapping['codigo'],
                $mapping['contacto'],
                $mapping['correo'],
            ]);

            if ($searchColumns !== []) {
                $query->where(function ($builder) use ($searchColumns, $q): void {
                    foreach ($searchColumns as $column) {
                        $builder->orWhere($column, 'like', "%{$q}%");
                    }
                });
            }
        }

        $contrato = trim((string) $request->query('contrato', ''));
        if ($contrato !== '' && $mapping['contrato']) {
            $query->where($mapping['contrato'], $contrato);
        }

        if ($mapping['nombre']) {
            $query->orderBy($mapping['nombre']);
        } elseif ($mapping['id']) {
            $query->orderByDesc($mapping['id']);
        }

        $clientes = $query->paginate(15)->withQueryString();

        $contratos = [];
        if ($mapping['contrato']) {
            $contratos = DB::table('clientes_potenciales')
                ->whereNotNull($mapping['contrato'])
                ->where($mapping['contrato'], '!=', '')
                ->distinct()
                ->orderBy($mapping['contrato'])
                ->pluck($mapping['contrato'])
                ->values()
                ->all();
        }

        return view('clientes.index', [
            'clientes' => $clientes,
            'filters' => [
                'q' => $q,
                'contrato' => $contrato,
            ],
            'contratos' => $contratos,
            'mapping' => $mapping,
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'mapping' => $this->resolveColumnMapping(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $payload = $this->buildPayload($request, $mapping);

        if ($payload === []) {
            return back()->withInput()->withErrors([
                'general' => 'No se encontraron columnas disponibles para guardar este cliente en la tabla clientes_potenciales.',
            ]);
        }

        DB::table('clientes_potenciales')->insert($payload);

        return redirect()->route('clientes.index')->with('status', 'Cliente creado correctamente.');
    }

    public function show(int $id): RedirectResponse
    {
        return redirect()->route('clientes.edit', $id);
    }

    public function edit(int $id): View
    {
        $mapping = $this->resolveColumnMapping();
        $query = DB::table('clientes_potenciales');

        if ($mapping['id']) {
            $query->where($mapping['id'], $id);
        }

        $cliente = $query->first();

        abort_if(!$cliente, 404);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'clienteId' => $id,
            'mapping' => $mapping,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $payload = $this->buildPayload($request, $mapping);

        if ($payload === []) {
            return back()->withInput()->withErrors([
                'general' => 'No se detectaron campos editables disponibles para actualizar.',
            ]);
        }

        $query = DB::table('clientes_potenciales');
        if ($mapping['id']) {
            $query->where($mapping['id'], $id);
        }

        $query->update($payload);

        return redirect()->route('clientes.index')->with('status', 'Cliente actualizado correctamente.');
    }

    public function retirar(int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $payload = [];

        if ($mapping['fecha_retiro']) {
            $payload[$mapping['fecha_retiro']] = Carbon::now()->toDateString();
        } elseif ($mapping['retirado_flag']) {
            $payload[$mapping['retirado_flag']] = 1;
        }

        if ($payload === []) {
            return back()->withErrors([
                'general' => 'No existe una columna de retiro (fecha_retiro/retirado) en clientes_potenciales para aplicar retiro lógico.',
            ]);
        }

        $query = DB::table('clientes_potenciales');
        if ($mapping['id']) {
            $query->where($mapping['id'], $id);
        }

        $query->update($payload);

        return redirect()->route('clientes.index')->with('status', 'Cliente marcado como retirado.');
    }

    private function buildPayload(Request $request, array $mapping): array
    {
        $payload = [];

        $inputToLogical = [
            'nit' => 'nit',
            'dv' => 'dv',
            'nombre' => 'nombre',
            'codigo' => 'codigo',
            'empresa' => 'empresa',
            'correo' => 'correo',
            'telefono' => 'telefono',
            'contacto' => 'contacto',
            'fecha_inicio' => 'fecha_inicio',
            'fecha_arriendo' => 'fecha_arriendo',
            'fecha_cotizacion' => 'fecha_cotizacion',
            'contrato' => 'contrato',
        ];

        foreach ($inputToLogical as $input => $logicalKey) {
            $column = $mapping[$logicalKey] ?? null;
            if (!$column) {
                continue;
            }

            if (!$request->has($input)) {
                continue;
            }

            $payload[$column] = $request->input($input) !== '' ? $request->input($input) : null;
        }

        return $payload;
    }

    private function resolveColumnMapping(): array
    {
        $columns = [];

        try {
            $columns = Schema::getColumnListing('clientes_potenciales');
        } catch (\Throwable) {
            $columns = [
                'idclientes_potenciales', 'nit', 'dv', 'nombre', 'codigo', 'empresa', 'email', 'correo', 'celular1', 'telefono',
                'contacto', 'fecha_inicio', 'fecha_arriendo', 'fecha_cotizacion', 'fecha_retiro', 'modalidad', 'contrato', 'retirado',
            ];
        }

        $pick = static function (array $candidates) use ($columns): ?string {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    return $candidate;
                }
            }

            return null;
        };

        return [
            'id' => $pick(['idclientes_potenciales', 'id']),
            'nit' => $pick(['nit']),
            'dv' => $pick(['dv']),
            'nombre' => $pick(['nombre']),
            'codigo' => $pick(['codigo']),
            'empresa' => $pick(['empresa', 'emp']),
            'correo' => $pick(['email', 'correo']),
            'telefono' => $pick(['celular1', 'telefono', 'celular']),
            'contacto' => $pick(['contacto']),
            'fecha_inicio' => $pick(['fecha_inicio', 'fechainicio']),
            'fecha_arriendo' => $pick(['fecha_arriendo']),
            'fecha_cotizacion' => $pick(['fecha_cotizacion']),
            'fecha_retiro' => $pick(['fecha_retiro']),
            'contrato' => $pick(['modalidad', 'contrato']),
            'retirado_flag' => $pick(['retirado']),
        ];
    }
}
