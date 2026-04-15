<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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
            $mapping['ip_empresa'] ? "{$mapping['ip_empresa']} as ip_empresa" : DB::raw('NULL as ip_empresa'),
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
            'catalogos' => $this->loadFormCatalogs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $catalogos = $this->loadFormCatalogs();

        $validated = $request->validate(
            $this->rules($catalogos, $mapping, true),
            $this->validationMessages()
        );
        $payload = $this->buildPayload($validated, $mapping, $catalogos);

        if ($payload === []) {
            return back()->withInput()->withErrors([
                'general' => 'No se encontraron columnas disponibles para guardar este cliente en la tabla clientes_potenciales.',
            ]);
        }

        if (Schema::hasColumn('clientes_potenciales', 'usuarios_idusuario')) {
            $payload['usuarios_idusuario'] = session('idusuario');
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
            'catalogos' => $this->loadFormCatalogs(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $catalogos = $this->loadFormCatalogs();

        $validated = $request->validate($this->rules($catalogos, $mapping));
        $payload = $this->buildPayload($validated, $mapping, $catalogos);

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

    private function buildPayload(array $validated, array $mapping, array $catalogos): array
    {
        $textInputsToUppercase = [
            'nit',
            'nombre',
            'codigo',
            'empresa',
            'departamento',
            'ip_empresa',
        ];

        foreach ($textInputsToUppercase as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $validated[$field] = $this->toUppercase($validated[$field]);
        }

        $payload = [];

        $inputToLogical = [
            'nit' => 'nit',
            'nombre' => 'nombre',
            'codigo' => 'codigo',
            'empresa' => 'empresa',
            'email' => 'email',
            'celular1' => 'celular1',
            'departamento' => 'departamento',
            'fecha_inicio' => 'fecha_llegada',
            'fecha_arriendo' => 'fecha_arriendo',
            'ip_empresa' => 'ip_empresa',
        ];

        foreach ($inputToLogical as $input => $logicalKey) {
            $column = $mapping[$logicalKey] ?? null;
            if (!$column) {
                continue;
            }

            if (!array_key_exists($input, $validated)) {
                continue;
            }

            $payload[$column] = $validated[$input] !== '' ? $validated[$input] : null;
        }

        $this->mapCatalogValue($payload, $validated, $mapping['clase'] ?? null, 'clase', $catalogos['clases']);
        $this->mapCatalogValue($payload, $validated, $mapping['modalidad'] ?? null, 'modalidad', $catalogos['modalidad']);
        $this->mapCatalogValue($payload, $validated, $mapping['llego'] ?? null, 'llego', $catalogos['llego']);

        return $payload;
    }

    private function mapCatalogValue(array &$payload, array $validated, ?string $targetColumn, string $inputKey, array $catalogo): void
    {
        if (!$targetColumn || !array_key_exists($inputKey, $validated)) {
            return;
        }

        $selectedId = $validated[$inputKey];
        if ($selectedId === null || $selectedId === '') {
            $payload[$targetColumn] = null;

            return;
        }

        $option = $catalogo['by_id'][(string) $selectedId] ?? null;
        if (!$option) {
            return;
        }

        $payload[$targetColumn] = $this->storesForeignId($targetColumn)
            ? $option['id']
            : $this->toUppercase($option['label']);
    }

    private function rules(array $catalogos, array $mapping, bool $withUnique = false): array
    {
        $rules = [
            'nit' => ['nullable', 'string', 'max:30'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'celular1' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_arriendo' => ['nullable', 'date'],
            'ip_empresa' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:150'],
            'clase' => $this->catalogRule($catalogos['clases']),
            'modalidad' => $this->catalogRule($catalogos['modalidad']),
            'llego' => $this->catalogRule($catalogos['llego']),
        ];

        if ($withUnique) {
            if ($mapping['nit']) {
                $rules['nit'][] = Rule::unique('clientes_potenciales', $mapping['nit']);
            }

            if ($mapping['codigo']) {
                $rules['codigo'][] = Rule::unique('clientes_potenciales', $mapping['codigo']);
            }
        }

        return $rules;
    }

    private function validationMessages(): array
    {
        return [
            'nit.unique' => 'El NIT ingresado ya existe en clientes potenciales.',
            'codigo.unique' => 'El código ingresado ya existe en clientes potenciales.',
        ];
    }

    private function toUppercase(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private function catalogRule(array $catalogo): array
    {
        if ($catalogo['ids'] === []) {
            return ['nullable'];
        }

        return ['nullable', Rule::in($catalogo['ids'])];
    }

    private function loadFormCatalogs(): array
    {
        return [
            'clases' => $this->loadCatalog('clases', ['idclases', 'id'], ['clase', 'nombre']),
            'modalidad' => $this->loadCatalog('modalidad', ['idmodalidad', 'id'], ['modalidad', 'nombre']),
            'llego' => $this->loadCatalog('llego', ['idllego', 'id'], ['llego', 'nombre']),
        ];
    }

    private function loadCatalog(string $table, array $idCandidates, array $labelCandidates): array
    {
        if (!Schema::hasTable($table)) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $columns = Schema::getColumnListing($table);
        $idColumn = $this->firstExistingColumn($columns, $idCandidates);
        $labelColumn = $this->firstExistingColumn($columns, $labelCandidates);

        if (!$idColumn || !$labelColumn) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $rows = DB::table($table)
            ->select([$idColumn, $labelColumn])
            ->orderBy($labelColumn)
            ->get();

        $options = [];
        $byId = [];
        $ids = [];

        foreach ($rows as $row) {
            $id = $row->{$idColumn};
            $label = $row->{$labelColumn};
            $item = [
                'id' => $id,
                'label' => (string) $label,
            ];

            $options[] = $item;
            $byId[(string) $id] = $item;
            $ids[] = (string) $id;
        }

        return [
            'options' => $options,
            'by_id' => $byId,
            'ids' => $ids,
        ];
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function storesForeignId(string $targetColumn): bool
    {
        try {
            $type = Schema::getColumnType('clientes_potenciales', $targetColumn);
        } catch (\Throwable) {
            return false;
        }

        return in_array($type, ['integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true);
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
            'email' => $pick(['email', 'correo']),
            'correo' => $pick(['email', 'correo']),
            'celular1' => $pick(['celular1', 'telefono', 'celular']),
            'telefono' => $pick(['celular1', 'telefono', 'celular']),
            'contacto' => $pick(['contacto']),

            'departamento' => $pick(['departamento']),

            'fecha_llegada' => $pick(['fecha_llegada', 'fecha_inicio', 'fechainicio']),
            'fecha_inicio' => $pick(['fecha_llegada', 'fecha_inicio', 'fechainicio']),
            'fecha_arriendo' => $pick(['fecha_arriendo']),
            'fecha_cotizacion' => $pick(['fecha_cotizacion']),
            'fecha_retiro' => $pick(['fecha_retiro']),
            'ip_empresa' => $pick(['ip_empresa']),
            'clase' => $pick(['clase', 'idclase', 'idclases']),
            'modalidad' => $pick(['modalidad']),
            'llego' => $pick(['llego', 'idllego']),
            'contrato' => $pick(['modalidad', 'contrato']),
            'retirado_flag' => $pick(['retirado']),
        ];
    }
}
