<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\ConfiguracionDirectorio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
            $mapping['retiro_flag'] ? "{$mapping['retiro_flag']} as retiro_flag" : DB::raw('NULL as retiro_flag'),
            $mapping['fecha_reactivacion'] ? "{$mapping['fecha_reactivacion']} as fecha_reactivacion" : DB::raw('NULL as fecha_reactivacion'),
            $mapping['motivo_reactivacion'] ? "{$mapping['motivo_reactivacion']} as motivo_reactivacion" : DB::raw('NULL as motivo_reactivacion'),
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
        $clientes->getCollection()->transform(function ($cliente) use ($mapping) {
            $cliente->esta_retirado = $this->isClienteRetirado($cliente, $mapping);

            return $cliente;
        });

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
            'motivosReactivacion' => $this->loadReactivationReasons(),
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'mapping' => $this->resolveColumnMapping(),
            'catalogos' => $this->loadFormCatalogs(),
        ]);
    }

    public function checkCodigoAvailability(Request $request): JsonResponse
    {
        $mapping = $this->resolveColumnMapping();
        $codigoColumn = $mapping['codigo'] ?? null;
        $codigo = $this->normalizeCodigo((string) $request->query('codigo', ''));

        if (!$codigoColumn) {
            return response()->json([
                'available' => false,
                'message' => 'La columna código no está disponible en esta instancia.',
            ], 422);
        }

        if ($codigo === '') {
            return response()->json([
                'available' => false,
                'message' => 'Escribe un código para validar.',
            ]);
        }

        $exists = DB::table('clientes_potenciales')
            ->whereRaw('UPPER(TRIM(' . $codigoColumn . ')) = ?', [$codigo])
            ->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Código en uso' : 'Código disponible',
        ]);
    }

    public function nextCodigo(Request $request): JsonResponse
    {
        $mapping = $this->resolveColumnMapping();
        $codigoColumn = $mapping['codigo'] ?? null;

        if (!$codigoColumn) {
            return response()->json([
                'message' => 'La columna código no está disponible en esta instancia.',
            ], 422);
        }

        $hint = $this->normalizeCodigo((string) $request->query('codigo', ''));
        $nextCodigo = $this->resolveNextCodigo($codigoColumn, $hint);

        if ($nextCodigo === null) {
            return response()->json([
                'message' => 'No fue posible calcular el siguiente consecutivo.',
            ], 422);
        }

        return response()->json([
            'codigo' => $nextCodigo,
            'message' => 'Código sugerido generado correctamente.',
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

        $clienteId = null;

        if ($mapping['id']) {
            $clienteId = DB::table('clientes_potenciales')->insertGetId($payload, $mapping['id']);
        } else {
            DB::table('clientes_potenciales')->insert($payload);
        }

        $this->crearEstructuraDirectoriosCliente($payload, $mapping, $clienteId);

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

        $cliente->esta_retirado = $this->isClienteRetirado($cliente, $mapping);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'clienteId' => $id,
            'mapping' => $mapping,
            'catalogos' => $this->loadFormCatalogs(),
            'motivosReactivacion' => $this->loadReactivationReasons(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $catalogos = $this->loadFormCatalogs();

        $validated = $request->validate(
            $this->rules($catalogos, $mapping),
            $this->validationMessages()
        );
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
        }

        if ($mapping['retiro_flag']) {
            $payload[$mapping['retiro_flag']] = 1;
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

    public function reactivar(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $motivosCatalogo = $this->loadReactivationReasons();

        $validated = $request->validate([
            'motivo_reactivacion' => array_merge(['required'], $this->catalogRule($motivosCatalogo)),
            'observacion_reactivacion' => ['nullable', 'string', 'max:2000'],
            'cliente_reactivacion_id' => ['nullable', 'integer'],
        ], [
            'motivo_reactivacion.in' => 'Selecciona un motivo de reactivación válido.',
        ]);

        if (
            !$mapping['fecha_reactivacion']
            && !$mapping['motivo_reactivacion']
            && !$mapping['fecha_retiro']
            && !$mapping['retiro_flag']
            && !$mapping['tipo_retiro']
        ) {
            return back()->withInput()->withErrors([
                'general' => 'No existen columnas disponibles para registrar la reactivación en clientes_potenciales.',
            ]);
        }

        $query = DB::table('clientes_potenciales');
        if ($mapping['id']) {
            $query->where($mapping['id'], $id);
        }

        $cliente = $query->first();

        if (!$cliente) {
            abort(404);
        }

        if (!$this->isClienteRetirado($cliente, $mapping)) {
            return back()->with('status', 'El cliente ya se encuentra activo.');
        }

        $payload = [];
        $hoy = Carbon::now()->toDateString();

        if ($mapping['fecha_reactivacion']) {
            $payload[$mapping['fecha_reactivacion']] = $hoy;
        }

        if ($mapping['motivo_reactivacion']) {
            $motivo = $motivosCatalogo['by_id'][(string) ($validated['motivo_reactivacion'] ?? '')] ?? null;
            $payload[$mapping['motivo_reactivacion']] = $motivo['label'] ?? null;
        }

        $motivoTexto = $mapping['motivo_reactivacion']
            ? ($payload[$mapping['motivo_reactivacion']] ?? null)
            : null;

        if ($mapping['retiro_flag']) {
            $payload[$mapping['retiro_flag']] = 0;
        }

        if ($mapping['fecha_retiro']) {
            $payload[$mapping['fecha_retiro']] = null;
        }

        if ($mapping['tipo_retiro']) {
            $payload[$mapping['tipo_retiro']] = null;
        }

        $observacion = trim((string) ($validated['observacion_reactivacion'] ?? ''));
        $commentColumn = $mapping['comentarios_reactivacion'] ?? null;
        if ($observacion !== '' && $commentColumn) {
            $payload[$commentColumn] = $this->buildReactivationComment(
                $cliente->{$commentColumn} ?? null,
                $hoy,
                $motivoTexto,
                $observacion
            );
        }

        $query->update($payload);

        return redirect()->route('clientes.index')->with('status', 'Cliente reactivado correctamente.');
    }

    private function buildPayload(array $validated, array $mapping, array $catalogos): array
    {
        $textInputsToUppercase = [
            'nit',
            'dv',
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
            'dv' => 'dv',
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
            'dv' => ['nullable', 'string', 'max:3', 'regex:/^[0-9xX]+$/'],
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
            'dv.max' => 'El DV no puede tener más de 3 caracteres.',
            'dv.regex' => 'El DV solo permite números y la letra X.',
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

    private function normalizeCodigo(string $codigo): string
    {
        return trim($this->toUppercase($codigo));
    }

    private function resolveNextCodigo(string $codigoColumn, string $hint = ''): ?string
    {
        $sequence = $this->extractCodigoSequence($hint);

        if ($sequence !== null) {
            $next = $this->findNextCodigoForPrefix($codigoColumn, $sequence['prefix']);

            if ($next !== null) {
                return $next;
            }

            return $sequence['prefix'] . str_pad((string) ($sequence['number'] + 1), $sequence['width'], '0', STR_PAD_LEFT);
        }

        if ($hint !== '') {
            $next = $this->findNextCodigoForPrefix($codigoColumn, $hint);

            if ($next !== null) {
                return $next;
            }
        }

        return $this->findNextCodigoForPrefix($codigoColumn, null);
    }

    private function findNextCodigoForPrefix(string $codigoColumn, ?string $prefix): ?string
    {
        $query = DB::table('clientes_potenciales')
            ->select($codigoColumn)
            ->whereNotNull($codigoColumn)
            ->where($codigoColumn, '!=', '');

        if ($prefix !== null && $prefix !== '') {
            $query->where($codigoColumn, 'like', $prefix . '%');
        }

        $codigos = $query->pluck($codigoColumn);

        $maxNumber = null;
        $maxWidth = 0;
        $resolvedPrefix = $prefix;

        foreach ($codigos as $codigo) {
            $sequence = $this->extractCodigoSequence((string) $codigo);

            if ($sequence === null) {
                continue;
            }

            if ($prefix !== null && $prefix !== '' && $sequence['prefix'] !== $prefix) {
                continue;
            }

            if ($maxNumber === null || $sequence['number'] > $maxNumber) {
                $maxNumber = $sequence['number'];
                $maxWidth = $sequence['width'];
                $resolvedPrefix = $sequence['prefix'];
                continue;
            }

            if ($sequence['number'] === $maxNumber && $sequence['width'] > $maxWidth) {
                $maxWidth = $sequence['width'];
            }
        }

        if ($maxNumber === null || $resolvedPrefix === null || $resolvedPrefix === '') {
            return null;
        }

        return $resolvedPrefix . str_pad((string) ($maxNumber + 1), $maxWidth, '0', STR_PAD_LEFT);
    }

    private function extractCodigoSequence(string $codigo): ?array
    {
        $normalized = $this->normalizeCodigo($codigo);

        if ($normalized === '' || !preg_match('/^([A-Z]+)(\d+)$/', $normalized, $matches)) {
            return null;
        }

        return [
            'prefix' => $matches[1],
            'number' => (int) $matches[2],
            'width' => strlen($matches[2]),
        ];
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

    private function loadReactivationReasons(): array
    {
        if (!Schema::hasTable('motivos_re')) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $catalogo = $this->loadCatalog('motivos_re', ['id'], ['nombre']);

        if ($catalogo['options'] === [] || !Schema::hasColumn('motivos_re', 'activo')) {
            return $catalogo;
        }

        $rows = DB::table('motivos_re')
            ->select(['id', 'nombre'])
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get();

        $options = [];
        $byId = [];
        $ids = [];

        foreach ($rows as $row) {
            $item = [
                'id' => $row->id,
                'label' => (string) $row->nombre,
            ];

            $options[] = $item;
            $byId[(string) $row->id] = $item;
            $ids[] = (string) $row->id;
        }

        return [
            'options' => $options,
            'by_id' => $byId,
            'ids' => $ids,
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

    private function isClienteRetirado(object $cliente, array $mapping): bool
    {
        $fechaRetiro = $mapping['fecha_retiro'] ? ($cliente->{$mapping['fecha_retiro']} ?? null) : ($cliente->fecha_retiro ?? null);
        $retiroFlag = $mapping['retiro_flag'] ? ($cliente->{$mapping['retiro_flag']} ?? null) : ($cliente->retiro_flag ?? null);

        return !empty($fechaRetiro) || (int) $retiroFlag === 1;
    }

    private function buildReactivationComment(mixed $currentValue, string $fecha, ?string $motivo, string $observacion): string
    {
        $actual = trim((string) $currentValue);
        $prefijo = $actual !== '' ? $actual . PHP_EOL : '';
        $motivoTexto = $motivo ? " Motivo: {$motivo}." : '';

        return trim($prefijo . "[REACTIVACION {$fecha}]{$motivoTexto} {$observacion}");
    }


private function crearEstructuraDirectoriosCliente(array $payload, array $mapping, mixed $clienteId): void
{
    
    try {
        $config = ConfiguracionDirectorio::query()->first();
        $rutaBase = trim((string) ($config?->ruta_clientes ?? ''));



        if ($rutaBase === '') {
            Log::warning('No hay ruta base configurada para directorios de clientes.', [
                'cliente_id' => $clienteId,
            ]);
            return;
        }

        if (!file_exists($rutaBase)) {
            Log::error('Ruta base no existe', [
                'cliente_id' => $clienteId,
                'ruta_base' => $rutaBase,
            ]);
            return;
        }

        $codigo = (string) ($payload[$mapping['codigo'] ?? ''] ?? '');
        $empresa = (string) ($payload[$mapping['empresa'] ?? ''] ?? '');
        $nombreEmpresa = $this->normalizeFolderName($codigo . '__' . $empresa);

        

        if ($nombreEmpresa === '__') {
            Log::warning('No se pudo generar nombre de carpeta para cliente.', [
                'cliente_id' => $clienteId,
                'ruta_base' => $rutaBase,
            ]);
            return;
        }

        $rutaFinal = $this->joinWindowsPath($rutaBase, $nombreEmpresa);

        File::makeDirectory($rutaFinal, 0777, true, true);

        foreach ($this->subcarpetasCliente() as $subcarpeta) {
            $rutaSubcarpeta = $this->joinWindowsPath($rutaFinal, $subcarpeta);
            File::makeDirectory($rutaSubcarpeta, 0777, true, true);
        }

        Log::info('Carpeta creada', [
            'cliente_id' => $clienteId,
            'ruta_base' => $rutaBase,
            'ruta_final' => $rutaFinal,
        ]);

    } catch (\Throwable $exception) {
    Log::error('Error al crear carpeta de cliente.', [
        'cliente_id' => $clienteId,
        'ruta_base' => $rutaBase ?? null,
        'error' => $exception->getMessage(),
    ]);
    }
}

private function normalizeFolderName(string $value): string
{
    $value = trim($value);
    $value = $this->removeAccents($value);
    $value = $this->toUppercase($value);

    $value = preg_replace('/[\\\\\/:*?"<>|]/', ' ', $value) ?? $value;
    $value = str_replace(['.', ',', ';'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
}

    private function removeAccents(string $value): string
    {
        $replacements = [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
            'á' => 'A',
            'é' => 'E',
            'í' => 'I',
            'ó' => 'O',
            'ú' => 'U',
            'ñ' => 'N',
        ];

        return strtr($value, $replacements);
    }

    private function joinWindowsPath(string $basePath, string $segment): string
    {
        return rtrim($basePath, '\\') . DIRECTORY_SEPARATOR . ltrim($segment, '\\');
    }

    private function subcarpetasCliente(): array
    {
        return [
            $this->normalizeFolderName('Capacitaciones'),
            $this->normalizeFolderName('Cartera'),
            $this->normalizeFolderName('Desarrollo de software'),
            $this->normalizeFolderName('Documentos'),
            $this->normalizeFolderName('Documentos historicos'),
            $this->normalizeFolderName('Equipos de computo'),
            $this->normalizeFolderName('Sistemas de informacion'),
            $this->normalizeFolderName('soporte'),
        ];
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
            'fecha_reactivacion' => $pick(['freact']),
            'ip_empresa' => $pick(['ip_empresa']),
            'clase' => $pick(['clase', 'idclase', 'idclases']),
            'modalidad' => $pick(['modalidad']),
            'llego' => $pick(['llego', 'idllego']),
            'contrato' => $pick(['modalidad', 'contrato']),
            'retiro_flag' => $pick(['retiro', 'retirado']),
            'motivo_reactivacion' => $pick(['mreact']),
            'tipo_retiro' => $pick(['tipoRetiro']),
            'comentarios_reactivacion' => $pick(['Comentarios', 'notas']),
        ];
    }
}
