<?php

namespace App\Http\Controllers;

use App\Models\ClientePotencial;
use App\Services\ClienteValorTotalCalculator;
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
use App\Services\TarifaConfigService;

class ClientesController extends Controller
{
    public function __construct(
        private readonly ClienteValorTotalCalculator $clienteValorTotalCalculator,
        private readonly TarifaConfigService $tarifaConfigService,
    ) {
    }

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
            $mapping['tipo_retiro'] ? "{$mapping['tipo_retiro']} as tipo_retiro" : DB::raw('NULL as tipo_retiro'),
            $mapping['fecha_reactivacion'] ? "{$mapping['fecha_reactivacion']} as fecha_reactivacion" : DB::raw('NULL as fecha_reactivacion'),
            $mapping['motivo_reactivacion'] ? "{$mapping['motivo_reactivacion']} as motivo_reactivacion" : DB::raw('NULL as motivo_reactivacion'),
            $mapping['ip_empresa'] ? "{$mapping['ip_empresa']} as ip_empresa" : DB::raw('NULL as ip_empresa'),
            $mapping['contrato'] ? "{$mapping['contrato']} as contrato" : DB::raw('NULL as contrato'),
            $mapping['estado_facturacion'] ? "{$mapping['estado_facturacion']} as estado_facturacion" : DB::raw("'".ClientePotencial::ESTADO_FACTURACION_ACTIVO."' as estado_facturacion"),
            $mapping['fecha_inicio_facturacion'] ? "{$mapping['fecha_inicio_facturacion']} as fecha_inicio_facturacion" : DB::raw('NULL as fecha_inicio_facturacion'),
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

        $motivosRetiro = $this->loadRetiroReasons();
        $clientes = $query->paginate(15)->withQueryString();
        $clientes->getCollection()->transform(function ($cliente) use ($mapping, $motivosRetiro) {
            $cliente->esta_retirado = $this->isClienteRetirado($cliente, $mapping);
            $cliente->motivo_retiro_nombre = $this->resolveRetiroReasonLabel($cliente, $mapping, $motivosRetiro);
            $cliente->estado_facturacion_normalizado = $this->resolveBillingStatus($cliente, $mapping);

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
            'motivosRetiro' => $motivosRetiro,
            'estadosFacturacion' => ClientePotencial::estadosFacturacion(),
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'mapping' => $this->resolveColumnMapping(),
            'catalogos' => $this->loadFormCatalogs(),
            'tarifasDefaults' => $this->tarifaConfigService->clientCreateDefaults(),
            'estadosFacturacion' => ClientePotencial::estadosFacturacion(),
            'selectedCity' => null,
        ]);
    }

    public function checkCodigoAvailability(Request $request): JsonResponse
    {
        $mapping = $this->resolveColumnMapping();
        $codigoColumn = $mapping['codigo'] ?? null;
        $codigo = $this->normalizeCodigo((string) $request->query('codigo', ''));
        $excludeId = $request->query('exclude_id');

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

        $conflictingClient = $this->findCodigoConflict($codigo, $mapping, $excludeId);
        $exists = $conflictingClient !== null;

        return response()->json([
            'available' => !$exists,
            'message' => $exists
                ? "El código {$codigo} ya está siendo utilizado por otro cliente."
                : 'Código disponible',
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
        $validated = $this->synchronizeSelectedCity($validated);
        $validated = $this->normalizeClientTextInputs($validated);

        if (($validated['codigo'] ?? '') !== '') {
            $codigo = $this->normalizeCodigo((string) $validated['codigo']);
            $conflictingClient = $this->findCodigoConflict($codigo, $mapping);

            if ($conflictingClient !== null) {
                return back()->withInput()->withErrors([
                    'codigo' => "El código {$codigo} ya está siendo utilizado por otro cliente.",
                ]);
            }
        }

        $payload = $this->buildPayload($validated, $mapping, $catalogos);
        $this->applyBillingDefaultsForCreate($payload, $validated, $mapping);

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
        $motivosRetiro = $this->loadRetiroReasons();
        $cliente->motivo_retiro_nombre = $this->resolveRetiroReasonLabel($cliente, $mapping, $motivosRetiro);
        $cliente->estado_facturacion_normalizado = $this->resolveBillingStatus($cliente, $mapping);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'clienteId' => $id,
            'mapping' => $mapping,
            'catalogos' => $this->loadFormCatalogs(),
            'motivosReactivacion' => $this->loadReactivationReasons(),
            'motivosRetiro' => $motivosRetiro,
            'estadosFacturacion' => ClientePotencial::estadosFacturacion(),
            'selectedCity' => $this->resolveSelectedCityFromStoredValue(
                $mapping['departamento'] ? ($cliente->{$mapping['departamento']} ?? null) : null
            ),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $catalogos = $this->loadFormCatalogs();
        $clienteActual = $this->findClienteById($id, $mapping);

        abort_if(!$clienteActual, 404);

        $validated = $request->validate(
            $this->rules($catalogos, $mapping),
            $this->validationMessages()
        );
        $validated = $this->synchronizeSelectedCity($validated);
        $validated = $this->normalizeClientTextInputs($validated);

        if (($validated['codigo'] ?? '') !== '') {
            $codigo = $this->normalizeCodigo((string) $validated['codigo']);
            $conflictingClient = $this->findCodigoConflict($codigo, $mapping, $id);

            if ($conflictingClient !== null) {
                return back()->withInput()->withErrors([
                    'codigo' => "El código {$codigo} ya está siendo utilizado por otro cliente.",
                ]);
            }
        }

        $payload = $this->buildPayload($validated, $mapping, $catalogos);
        $this->applyBillingStateTransition($payload, $validated, $clienteActual, $mapping);

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

    public function activarFacturacion(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $cliente = $this->findClienteById($id, $mapping);

        abort_if(!$cliente, 404);

        if (!$mapping['estado_facturacion']) {
            return back()->withErrors([
                'general' => 'La columna estado_facturacion no esta disponible en clientes_potenciales.',
            ]);
        }

        $payload = [
            $mapping['estado_facturacion'] => ClientePotencial::ESTADO_FACTURACION_ACTIVO,
        ];

        if (
            $mapping['fecha_inicio_facturacion']
            && empty($cliente->{$mapping['fecha_inicio_facturacion']} ?? null)
        ) {
            $payload[$mapping['fecha_inicio_facturacion']] = Carbon::now()->toDateString();
        }

        DB::table('clientes_potenciales')
            ->where($mapping['id'], $id)
            ->update($payload);

        return back()
            ->with('status', 'Cliente activado para facturacion correctamente.')
            ->with('status_type', 'success');
    }

    public function retirar(Request $request, int $id): RedirectResponse
    {
        $mapping = $this->resolveColumnMapping();
        $motivosRetiro = $this->loadRetiroReasons();

        if (($motivosRetiro['ids'] ?? []) === []) {
            return back()->withErrors([
                'general' => 'No hay motivos de retiro disponibles en la tabla conceptos_r.',
            ]);
        }

        $validated = $request->validate([
            'motivo_retiro' => array_merge(['required'], $this->catalogRule($motivosRetiro)),
            'fecha_retiro' => ['required', 'date'],
            'cliente_retiro_id' => ['nullable', 'integer'],
        ], [
            'motivo_retiro.required' => 'Selecciona un motivo de retiro.',
            'motivo_retiro.in' => 'Selecciona un motivo de retiro válido.',
            'fecha_retiro.required' => 'Selecciona una fecha de retiro.',
            'fecha_retiro.date' => 'Selecciona una fecha de retiro válida.',
        ]);
        $payload = [];

        if ($mapping['fecha_retiro']) {
            $payload[$mapping['fecha_retiro']] = $validated['fecha_retiro'];
        }

        if ($mapping['retiro_flag']) {
            $payload[$mapping['retiro_flag']] = 1;
        }

        if ($mapping['tipo_retiro']) {
            $payload[$mapping['tipo_retiro']] = $validated['motivo_retiro'];
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
        $validated = $this->normalizeClientTextInputs($validated);

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
            'regimen' => 'regimen',
            'estado_facturacion' => 'estado_facturacion',
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
        $this->mapCatalogValue($payload, $validated, $mapping['tipo_cliente'] ?? null, 'tipo_cliente_id', $catalogos['tipos_cliente']);

        $numericInputsToLogical = [
            'vlrprincipal' => 'vlrprincipal',
            'numequipos' => 'numequipos',
            'vlrterminal' => 'vlrterminal',
            'vlrterminal_recepcion' => 'vlrterminal_recepcion',
            'vlrnomina' => 'vlrnomina',
            'nominaterminal' => 'nominaterminal',
            'vlrterminal_nomina' => 'vlrterminal_nomina',
            'vlracuse' => 'vlracuse',
            'vlrfactura' => 'vlrfactura',
            'vlrsoporte' => 'vlrsoporte',
            'vlrextra' => 'vlrextra',
            'vlrextra2' => 'vlrextra2',
            'numeromoviles' => 'numeromoviles',
            'vlrmovil' => 'vlrmovil',
            'numextra' => 'numextra',
            'vlrextrae' => 'vlrextrae',
        ];

        foreach ($numericInputsToLogical as $input => $logicalKey) {
            $column = $mapping[$logicalKey] ?? null;
            if (!$column || !array_key_exists($input, $validated)) {
                continue;
            }

            $payload[$column] = $validated[$input] !== '' && $validated[$input] !== null
                ? (float) $validated[$input]
                : null;
        }

        if (($mapping['valor_total'] ?? null) && array_key_exists('valor_total', $validated)) {
            $payload[$mapping['valor_total']] = $this->clienteValorTotalCalculator->calculate($validated);
        }

        return $payload;
    }

    private function applyBillingDefaultsForCreate(array &$payload, array $validated, array $mapping): void
    {
        if ($mapping['estado_facturacion']) {
            $payload[$mapping['estado_facturacion']] = ClientePotencial::normalizeEstadoFacturacion(
                $validated['estado_facturacion'] ?? null,
                ClientePotencial::ESTADO_FACTURACION_PENDIENTE
            );
        }

        if (!$mapping['fecha_inicio_facturacion']) {
            return;
        }

        $estadoDestino = ClientePotencial::normalizeEstadoFacturacion(
            $validated['estado_facturacion'] ?? null,
            ClientePotencial::ESTADO_FACTURACION_PENDIENTE
        );

        $payload[$mapping['fecha_inicio_facturacion']] = $estadoDestino === ClientePotencial::ESTADO_FACTURACION_ACTIVO
            ? Carbon::now()->toDateString()
            : null;
    }

    private function applyBillingStateTransition(array &$payload, array $validated, object $clienteActual, array $mapping): void
    {
        if (!$mapping['estado_facturacion']) {
            return;
        }

        $estadoActual = $this->resolveBillingStatus($clienteActual, $mapping);
        $estadoDestino = ClientePotencial::normalizeEstadoFacturacion(
            $validated['estado_facturacion'] ?? null,
            $estadoActual
        );

        $payload[$mapping['estado_facturacion']] = $estadoDestino;

        if (
            $estadoActual === ClientePotencial::ESTADO_FACTURACION_PENDIENTE
            && $estadoDestino === ClientePotencial::ESTADO_FACTURACION_ACTIVO
            && $mapping['fecha_inicio_facturacion']
            && empty($clienteActual->{$mapping['fecha_inicio_facturacion']} ?? null)
        ) {
            $payload[$mapping['fecha_inicio_facturacion']] = Carbon::now()->toDateString();
        }
    }

    private function resolveBillingStatus(object $cliente, array $mapping): string
    {
        $column = $mapping['estado_facturacion'] ?? null;
        $estado = $column ? ($cliente->{$column} ?? null) : ($cliente->estado_facturacion ?? null);

        return ClientePotencial::normalizeEstadoFacturacion($estado);
    }

    private function findClienteById(int $id, array $mapping): ?object
    {
        if (!$mapping['id']) {
            return null;
        }

        return DB::table('clientes_potenciales')
            ->where($mapping['id'], $id)
            ->first();
    }

    private function findCodigoConflict(string $codigo, array $mapping, mixed $excludeId = null): ?object
    {
        $codigoColumn = $mapping['codigo'] ?? null;

        if (!$codigoColumn) {
            return null;
        }

        $normalizedCodigo = $this->normalizeCodigo($codigo);

        if ($normalizedCodigo === '') {
            return null;
        }

        $query = DB::table('clientes_potenciales')
            ->select($mapping['id'] ? "{$mapping['id']} as id" : DB::raw('NULL as id'))
            ->whereRaw('UPPER(TRIM(' . $codigoColumn . ')) = ?', [$normalizedCodigo]);

        if ($excludeId !== null && $excludeId !== '' && $mapping['id']) {
            $query->where($mapping['id'], '!=', $excludeId);
        }

        return $query->first();
    }

    private function normalizeClientTextInputs(array $validated): array
    {
        foreach ($this->clientTextInputsToUppercase() as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $validated[$field] = $this->toUppercase($validated[$field]);
        }

        return $validated;
    }

    private function synchronizeSelectedCity(array $validated): array
    {
        if (!array_key_exists('ciudad_codigo', $validated)) {
            return $validated;
        }

        $selectedCity = $this->findCityByCode((string) $validated['ciudad_codigo']);

        if ($selectedCity !== null) {
            $validated['departamento'] = $selectedCity['label'];
        }

        return $validated;
    }

    private function clientTextInputsToUppercase(): array
    {
        return [
            'nit',
            'dv',
            'nombre',
            'codigo',
            'empresa',
            'celular1',
            'departamento',
            'ip_empresa',
            'regimen',
            'estado_facturacion',
        ];
    }

    private function mapCatalogValue(array &$payload, array $validated, ?string $targetColumn, string $inputKey, array $catalogo): void
    {
        if (!$targetColumn || !array_key_exists($inputKey, $validated)) {
            return;
        }

        $selectedId = $this->normalizeCatalogSelection($validated[$inputKey], $catalogo);
        if ($selectedId === null || $selectedId === '') {
            $payload[$targetColumn] = null;

            return;
        }

        $option = $catalogo['by_id'][(string) $selectedId] ?? null;
        if (!$option) {
            return;
        }

        $payload[$targetColumn] = $this->storesForeignId($targetColumn)
            ? (int) $option['id']
            : $this->toUppercase($option['label']);
    }

    private function normalizeCatalogSelection(mixed $value, array $catalogo): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (isset($catalogo['by_id'][(string) $value])) {
            return $value;
        }

        $normalizedValue = trim($this->toUppercase((string) $value));

        foreach ($catalogo['options'] ?? [] as $option) {
            $normalizedLabel = trim($this->toUppercase((string) ($option['label'] ?? '')));

            if ($normalizedLabel === $normalizedValue) {
                return $option['id'];
            }
        }

        return $value;
    }

    private function rules(array $catalogos, array $mapping, bool $withUnique = false): array
    {
        $rules = [
            'nit' => $this->requiredTextRules($mapping['nit'], ['max:30']),
            'dv' => $this->requiredTextRules($mapping['dv'], ['max:3', 'regex:/^[0-9xX]+$/']),
            'nombre' => $this->requiredTextRules($mapping['nombre'], ['max:150']),
            'empresa' => $this->requiredTextRules($mapping['empresa'], ['max:150']),
            'celular1' => $this->requiredTextRules($mapping['celular1'], ['max:30']),
            'email' => [
                ...$this->requiredRule($mapping['email']),
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->isValidEmailList($value)) {
                        $fail('Debe ingresar un correo valido o varios correos validos separados por coma o punto y coma.');
                    }
                },
            ],
            'codigo' => $this->requiredTextRules($mapping['codigo'], ['max:50']),
            'fecha_inicio' => $this->requiredRule($mapping['fecha_llegada'], ['date']),
            'fecha_arriendo' => $this->requiredRule($mapping['fecha_arriendo'], ['date']),
            'ip_empresa' => $this->requiredTextRules($mapping['ip_empresa'], ['max:255']),
            'departamento' => array_merge(
                $this->requiredTextRules($mapping['departamento'], ['max:150']),
                [
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        $requestCityCode = request()->input('ciudad_codigo');
                        $selectedCity = $this->findCityByCode((string) $requestCityCode);
                        $expectedLabel = $selectedCity['label'] ?? null;

                        if ($expectedLabel === null) {
                            return;
                        }

                        if ($this->normalizeComparableText((string) $value) !== $this->normalizeComparableText($expectedLabel)) {
                            $fail('La ciudad seleccionada no coincide con el catalogo oficial.');
                        }
                    },
                ]
            ),
            'ciudad_codigo' => $this->citySelectionRules($mapping['departamento']),
            'regimen' => $this->requiredRule($mapping['regimen'], [Rule::in(['SAS', 'PCS', 'SMP'])]),
            'estado_facturacion' => $this->requiredRule($mapping['estado_facturacion'], [Rule::in(ClientePotencial::estadosFacturacion())]),
            'vlrprincipal' => ['nullable', 'numeric', 'min:0'],
            'numequipos' => ['nullable', 'numeric', 'min:0'],
            'vlrterminal' => ['nullable', 'numeric', 'min:0'],
            'vlrterminal_recepcion' => ['nullable', 'numeric', 'min:0'],
            'vlrnomina' => ['nullable', 'numeric', 'min:0'],
            'nominaterminal' => ['nullable', 'numeric', 'min:0'],
            'vlrterminal_nomina' => ['nullable', 'numeric', 'min:0'],
            'vlracuse' => ['nullable', 'numeric', 'min:0'],
            'vlrfactura' => ['nullable', 'numeric', 'min:0'],
            'vlrsoporte' => ['nullable', 'numeric', 'min:0'],
            'vlrextra' => ['nullable', 'numeric', 'min:0'],
            'vlrextra2' => ['nullable', 'numeric', 'min:0'],
            'numeromoviles' => ['nullable', 'numeric', 'min:0'],
            'vlrmovil' => ['nullable', 'numeric', 'min:0'],
            'numextra' => ['nullable', 'numeric', 'min:0'],
            'vlrextrae' => ['nullable', 'numeric', 'min:0'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'clase' => $this->catalogRule($catalogos['clases'], $mapping['clase']),
            'modalidad' => $this->catalogRule($catalogos['modalidad'], $mapping['modalidad']),
            'llego' => $this->catalogRule($catalogos['llego'], $mapping['llego']),
            'tipo_cliente_id' => $this->catalogRule($catalogos['tipos_cliente'], $mapping['tipo_cliente']),
        ];

        if ($withUnique) {
            if ($mapping['nit']) {
                $rules['nit'][] = Rule::unique('clientes_potenciales', $mapping['nit']);
            }
        }

        return $rules;
    }

    private function validationMessages(): array
    {
        return [
            'nit.unique' => 'El NIT ingresado ya existe en clientes potenciales.',
            'ciudad_codigo.required' => 'Debes buscar y seleccionar una ciudad valida del catalogo.',
            'ciudad_codigo.exists' => 'Debes buscar y seleccionar una ciudad valida del catalogo.',
            'dv.max' => 'El DV no puede tener más de 3 caracteres.',
            'dv.regex' => 'El DV solo permite números y la letra X.',
            'regimen.in' => 'Selecciona un regimen válido: SAS, PCS o SMP.',
        ];
    }

    private function isValidEmailList(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return true;
        }

        $emails = preg_split('/[;,]/', $raw) ?: [];
        $validEmails = 0;

        foreach ($emails as $email) {
            $email = trim((string) $email);

            if ($email === '') {
                return false;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            $validEmails++;
        }

        return $validEmails > 0;
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

    private function catalogRule(array $catalogo, ?string $mappedColumn = null): array
    {
        if ($mappedColumn === null) {
            return ['nullable'];
        }

        if ($catalogo['ids'] === []) {
            return ['nullable'];
        }

        return ['required', Rule::in($catalogo['ids'])];
    }

    private function requiredRule(?string $mappedColumn, array $extraRules = []): array
    {
        return array_merge($mappedColumn ? ['required'] : ['nullable'], $extraRules);
    }

    private function requiredTextRules(?string $mappedColumn, array $extraRules = []): array
    {
        return $this->requiredRule($mappedColumn, array_merge(['string'], $extraRules));
    }

    private function citySelectionRules(?string $mappedColumn): array
    {
        if ($mappedColumn === null) {
            return ['nullable'];
        }

        if (
            !Schema::hasTable('xxxxcity')
            || !Schema::hasColumn('xxxxcity', 'citycodigo')
            || !Schema::hasColumn('xxxxcity', 'citynomb')
            || !Schema::hasColumn('xxxxcity', 'citydepto')
        ) {
            return [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $fail('El catalogo oficial de ciudades no esta disponible.');
                },
            ];
        }

        return ['required', Rule::exists('xxxxcity', 'citycodigo')];
    }

    private function findCityByCode(string $cityCode): ?array
    {
        $cityCode = trim($cityCode);

        if (
            $cityCode === ''
            || !Schema::hasTable('xxxxcity')
            || !Schema::hasColumn('xxxxcity', 'citycodigo')
            || !Schema::hasColumn('xxxxcity', 'citynomb')
            || !Schema::hasColumn('xxxxcity', 'citydepto')
        ) {
            return null;
        }

        $city = DB::table('xxxxcity')
            ->select(['citycodigo', 'citynomb', 'citydepto', 'cityNdepto'])
            ->where('citycodigo', $cityCode)
            ->first();

        if (!$city) {
            return null;
        }

        return [
            'code' => (string) $city->citycodigo,
            'label' => $this->formatCityLabel($city->citynomb, $city->cityNdepto ?? $city->citydepto),
        ];
    }

    private function resolveSelectedCityFromStoredValue(mixed $storedValue): ?array
    {
        $storedValue = (string) $storedValue;
        $normalizedStoredValue = $this->normalizeComparableText($storedValue);

        if ($normalizedStoredValue === '') {
            return null;
        }

        if (
            !Schema::hasTable('xxxxcity')
            || !Schema::hasColumn('xxxxcity', 'citycodigo')
            || !Schema::hasColumn('xxxxcity', 'citynomb')
            || !Schema::hasColumn('xxxxcity', 'citydepto')
        ) {
            return null;
        }

        [$cityTerm, $departmentTerm] = array_pad(array_map('trim', explode(',', $storedValue, 2)), 2, '');

        $cities = DB::table('xxxxcity')
            ->select(['citycodigo', 'citynomb', 'citydepto', 'cityNdepto'])
            ->where(function ($query) use ($storedValue, $cityTerm, $departmentTerm): void {
                $query->where('citynomb', $storedValue)
                    ->orWhere('citydepto', $storedValue)
                    ->orWhere('cityNdepto', $storedValue);

                if ($cityTerm !== '') {
                    $query->orWhere('citynomb', $cityTerm);
                }

                if ($departmentTerm !== '') {
                    $query->orWhere('citydepto', $departmentTerm)
                        ->orWhere('cityNdepto', $departmentTerm);
                }
            })
            ->get();

        foreach ($cities as $city) {
            $formattedLabel = $this->formatCityLabel($city->citynomb, $city->cityNdepto ?? $city->citydepto);

            if (
                $this->normalizeComparableText((string) $city->citynomb) === $normalizedStoredValue
                || $this->normalizeComparableText($formattedLabel) === $normalizedStoredValue
            ) {
                return [
                    'code' => (string) $city->citycodigo,
                    'label' => $formattedLabel,
                ];
            }
        }

        return null;
    }

    private function formatCityLabel(mixed $cityName, mixed $departmentName): string
    {
        $cityName = trim($this->toUppercase((string) $cityName));
        $departmentName = trim($this->toUppercase((string) $departmentName));

        if ($cityName === '') {
            return $departmentName;
        }

        if ($departmentName === '') {
            return $cityName;
        }

        if (str_ends_with($cityName, ', ' . $departmentName)) {
            return $cityName;
        }

        return "{$cityName}, {$departmentName}";
    }

    private function normalizeComparableText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($this->toUppercase($value))) ?? '';
    }

    private function loadFormCatalogs(): array
    {
        return [
            'clases' => $this->loadCatalog('clases', ['idclases', 'id'], ['clase', 'nombre']),
            'modalidad' => $this->loadCatalog('modalidad', ['idmodalidad', 'id'], ['modalidad', 'nombre']),
            'llego' => $this->loadCatalog('llego', ['idllego', 'id'], ['llego', 'nombre']),
            'tipos_cliente' => $this->loadCatalog(
                'tipos_cliente',
                ['id'],
                ['nombre'],
                activeColumnCandidates: ['activo'],
                orderColumnCandidates: ['orden', 'nombre']
            ),
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

    private function loadRetiroReasons(): array
    {
        if (!Schema::hasTable('conceptos_r')) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $rows = DB::table('conceptos_r')
            ->select(['id_retiro', 'conceptosretiro'])
            ->orderBy('conceptosretiro')
            ->get();

        $options = [];
        $byId = [];
        $ids = [];

        foreach ($rows as $row) {
            $item = [
                'id' => (string) $row->id_retiro,
                'label' => (string) $row->conceptosretiro,
            ];

            $options[] = $item;
            $byId[(string) $row->id_retiro] = $item;
            $ids[] = (string) $row->id_retiro;
        }

        return [
            'options' => $options,
            'by_id' => $byId,
            'ids' => $ids,
        ];
    }

    private function loadCatalog(
        string $table,
        array $idCandidates,
        array $labelCandidates,
        array $activeColumnCandidates = [],
        array $orderColumnCandidates = []
    ): array
    {
        if (!Schema::hasTable($table)) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $columns = Schema::getColumnListing($table);
        $idColumn = $this->firstExistingColumn($columns, $idCandidates);
        $labelColumn = $this->firstExistingColumn($columns, $labelCandidates);
        $activeColumn = $this->firstExistingColumn($columns, $activeColumnCandidates);
        $orderColumn = $this->firstExistingColumn($columns, $orderColumnCandidates);

        if (!$idColumn || !$labelColumn) {
            return ['options' => [], 'by_id' => [], 'ids' => []];
        }

        $query = DB::table($table)
            ->select([$idColumn, $labelColumn]);

        if ($activeColumn) {
            $query->where($activeColumn, 1);
        }

        if ($orderColumn) {
            $query->orderBy($orderColumn);
        } else {
            $query->orderBy($labelColumn);
        }

        $rows = $query->get();

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
        if ($targetColumn === 'tipo_cliente_id') {
            return true;
        }

        try {
            $type = Schema::getColumnType('clientes_potenciales', $targetColumn);
        } catch (\Throwable) {
            return false;
        }

        return in_array($type, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true);
    }

    private function isClienteRetirado(object $cliente, array $mapping): bool
    {
        $fechaRetiro = $mapping['fecha_retiro'] ? ($cliente->{$mapping['fecha_retiro']} ?? null) : ($cliente->fecha_retiro ?? null);
        $retiroFlag = $mapping['retiro_flag'] ? ($cliente->{$mapping['retiro_flag']} ?? null) : ($cliente->retiro_flag ?? null);

        return !empty($fechaRetiro) || (int) $retiroFlag === 1;
    }

    private function resolveRetiroReasonLabel(object $cliente, array $mapping, array $catalogo): ?string
    {
        $tipoRetiroColumn = $mapping['tipo_retiro'] ?? null;
        $tipoRetiro = $tipoRetiroColumn ? ($cliente->{$tipoRetiroColumn} ?? null) : ($cliente->tipo_retiro ?? null);

        if ($tipoRetiro === null || $tipoRetiro === '') {
            return null;
        }

        return $catalogo['by_id'][(string) $tipoRetiro]['label'] ?? null;
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

        foreach ($this->subcarpetasCliente() as $subcarpeta => $subcarpetasInternas) {
            $rutaSubcarpeta = $this->joinWindowsPath($rutaFinal, $subcarpeta);
            File::makeDirectory($rutaSubcarpeta, 0777, true, true);

            foreach ($subcarpetasInternas as $subcarpetaInterna) {
                $rutaSubcarpetaInterna = $this->joinWindowsPath($rutaSubcarpeta, $subcarpetaInterna);
                File::makeDirectory($rutaSubcarpetaInterna, 0777, true, true);
            }
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
            $this->normalizeFolderName('Capacitaciones') => [
                $this->normalizeFolderName('ACTAS'),
            ],
            $this->normalizeFolderName('Cartera') => [
                $this->normalizeFolderName('SOPORTES DE PAGO'),
            ],
            $this->normalizeFolderName('Desarrollo de software') => [],
            $this->normalizeFolderName('Documentos') => [
                $this->normalizeFolderName('AUTORIZACIONES'),
                $this->normalizeFolderName('CONTRATOS'),
                $this->normalizeFolderName('COTIZACIONES'),
                $this->normalizeFolderName('ELECTRONICOS'),
                $this->normalizeFolderName('LEGALES'),
            ],
            $this->normalizeFolderName('Documentos historicos') => [],
            $this->normalizeFolderName('Equipos de computo') => [
                $this->normalizeFolderName('GARANTIAS CAMBIOS'),
                $this->normalizeFolderName('VENTA EQUIPOS'),
            ],
            $this->normalizeFolderName('Sistemas de informacion') => [
                $this->normalizeFolderName('ARCHIVOS INSTALACION'),
                $this->normalizeFolderName('FORMATOS'),
                $this->normalizeFolderName('LOGOS GRAFICOS'),
            ],
            $this->normalizeFolderName('soporte') => [
                $this->normalizeFolderName('CORRESPONDENCIA'),
            ],
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
            'regimen' => $pick(['regimen']),
            'clase' => $pick(['clase', 'idclase', 'idclases']),
            'modalidad' => $pick(['modalidad']),
            'llego' => $pick(['llego', 'idllego']),
            'tipo_cliente' => $pick(['tipo_cliente_id']),
            'contrato' => $pick(['modalidad', 'contrato']),
            'vlrprincipal' => $pick(['vlrprincipal']),
            'numequipos' => $pick(['numequipos']),
            'vlrterminal' => $pick(['vlrterminal']),
            'vlrterminal_recepcion' => $pick(['vlrterminal_recepcion', 'vlrterminalrecepcion']),
            'vlrnomina' => $pick(['vlrnomina']),
            'nominaterminal' => $pick(['nominaterminal']),
            'vlrterminal_nomina' => $pick(['vlrterminalnomina', 'vlrterminal_nomina', 'vlrnominaterminal']),
            'vlracuse' => $pick(['vlrecepcion', 'vlrrecepcion', 'vlracuse']),
            'vlrfactura' => $pick(['vlrfactura']),
            'vlrsoporte' => $pick(['vlrsoporte']),
            'vlrextra' => $pick(['vlrextra']),
            'vlrextra2' => $pick(['vlrextra2']),
            'numeromoviles' => $pick(['numeromoviles']),
            'vlrmovil' => $pick(['vlrmovil']),
            'numextra' => $pick(['numextra']),
            'vlrextrae' => $pick(['vlrextrae']),
            'valor_total' => $pick(['valor_total']),
            'retiro_flag' => $pick(['retiro', 'retirado']),
            'motivo_reactivacion' => $pick(['mreact']),
            'tipo_retiro' => $pick(['tipoRetiro']),
            'comentarios_reactivacion' => $pick(['Comentarios', 'notas']),
            'estado_facturacion' => $pick(['estado_facturacion']),
            'fecha_inicio_facturacion' => $pick(['fecha_inicio_facturacion']),
        ];
    }
}
