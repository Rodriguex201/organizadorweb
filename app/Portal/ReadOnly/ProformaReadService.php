<?php

namespace App\Portal\ReadOnly;

use App\Services\ProformasService;

final class ProformaReadService
{
    // Fixed SQL, bound values. No MAX, LIMIT 1, historical fallback or locks.
    public const QUERIES = [
        'cliente' => 'SELECT idclientes_potenciales FROM clientes_potenciales WHERE BINARY TRIM(nit) = BINARY ? LIMIT 2',
        'proforma' => 'SELECT id, id_cobro, nro_prof, emp, mes, anio, vtotal, estado FROM sg_proform WHERE BINARY TRIM(nit) = BINARY ? AND BINARY nro_prof = BINARY ? LIMIT 2',
        'relacion' => 'SELECT DISTINCT id_cliente FROM valores_externos WHERE id_cobro = ? LIMIT 2',
        'detalle' => 'SELECT descripcion, cantidad, vr_unidad, vr_parcial FROM sg_proford WHERE proforma_id = ? ORDER BY orden LIMIT 501',
        'email' => 'SELECT email FROM clientes_potenciales WHERE idclientes_potenciales = ? LIMIT 2',
    ];

    public function __construct(private SelectReader $reader) {}

    public static function validInput(mixed $documento, mixed $numero): bool
    {
        return is_string($documento) && is_string($numero)
            && preg_match('/\A[0-9]{5,15}\z/', $documento) === 1
            && preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $numero) === 1;
    }

    private function identity(string $documento, string $numero): ?array
    {
        if (!self::validInput($documento, $numero)) {
            return null;
        }
        $clientes = $this->reader->select(self::QUERIES['cliente'], [$documento]);
        if (count($clientes) !== 1) {
            return null;
        }
        $proformas = $this->reader->select(self::QUERIES['proforma'], [$documento, $numero]);
        if (count($proformas) !== 1) {
            return null;
        }
        $p = $proformas[0];
        if (!self::positiveId($p['id_cobro']) || !self::positiveId($p['id'])
            || !self::positiveId($clientes[0]['idclientes_potenciales'])) {
            return null;
        }
        $relaciones = $this->reader->select(self::QUERIES['relacion'], [$p['id_cobro']]);
        // Deliberately reject even differently formatted duplicate IDs.
        if (count($relaciones) !== 1
            || !self::positiveId($relaciones[0]['id_cliente'])
            || (string) $relaciones[0]['id_cliente'] !== (string) $clientes[0]['idclientes_potenciales']) {
            return null;
        }
        return ['cliente_id' => (string) $clientes[0]['idclientes_potenciales'], 'proforma_id' => (string) $p['id'], 'header' => $p];
    }

    public function otpTarget(string $documento, string $numero): ?array
    {
        $identity = $this->identity($documento, $numero);
        if ($identity === null) return null;
        $rows = $this->reader->select(self::QUERIES['email'], [$identity['cliente_id']]);
        if (count($rows) !== 1) return null;
        unset($identity['header']);
        return $identity + ['email' => (string) ($rows[0]['email'] ?? ''), 'documento' => $documento, 'numero' => $numero];
    }

    public function authorizedDetail(array $grant): ?array
    {
        $identity = $this->identity($grant['documento'], $grant['numero']);
        if ($identity === null || $identity['cliente_id'] !== $grant['cliente_id']
            || $identity['proforma_id'] !== $grant['proforma_id']) return null;
        return $this->find($grant['documento'], $grant['numero']);
    }

    public function find(string $documento, string $numero): ?array
    {
        $identity = $this->identity($documento, $numero);
        if ($identity === null) return null;
        $p = $identity['header'];
        $lineas = $this->reader->select(self::QUERIES['detalle'], [$p['id']]);
        if ($lineas === [] || count($lineas) > 500) {
            return null;
        }
        if (!isset(ProformasService::MESES[(int) $p['mes']])
            || !preg_match('/\A[0-9]{4}\z/', (string) $p['anio'])
            || !self::decimal($p['vtotal'])) {
            return null;
        }
        foreach ($lineas as $linea) {
            foreach (['cantidad', 'vr_unidad', 'vr_parcial'] as $key) {
                if (!self::decimal($linea[$key])) {
                    return null;
                }
            }
        }
        // Whitelist the public DTO. Never pass the raw header or relationship.
        return [
            'numero' => $p['nro_prof'],
            'empresa' => $p['emp'],
            'periodo' => ucfirst(ProformasService::MESES[(int) $p['mes']]).' de '.$p['anio'],
            'estado' => ProformasService::ESTADOS[(int) $p['estado']] ?? 'No disponible',
            'total' => (string) $p['vtotal'],
            'lineas' => array_map(static fn (array $l): array => [
                'concepto' => $l['descripcion'],
                'cantidad' => (string) $l['cantidad'],
                'unitario' => (string) $l['vr_unidad'],
                'subtotal' => (string) $l['vr_parcial'],
            ], $lineas),
        ];
    }

    private static function positiveId(mixed $value): bool
    {
        return preg_match('/\A[1-9][0-9]*\z/', (string) $value) === 1;
    }

    private static function decimal(mixed $value): bool
    {
        return preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string) $value) === 1;
    }
}
