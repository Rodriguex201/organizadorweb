<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

class ClienteRetiradoService
{
    private ?string $fechaRetiroColumn = null;
    private bool $fechaRetiroResolved = false;
    private ?string $retiroFlagColumn = null;
    private bool $retiroFlagResolved = false;

    public function estaRetirado(null|object $cliente): bool
    {
        if (!$cliente) {
            return false;
        }

        $fechaRetiro = $this->firstAvailableValue($cliente, [
            'cliente_fecha_retiro',
            'fecha_retiro',
        ]);

        if (!empty($fechaRetiro)) {
            return true;
        }

        $retiroFlag = $this->firstAvailableValue($cliente, [
            'cliente_retiro_flag',
            'retiro_flag',
            'retirado',
            'retiro',
        ]);

        return (int) $retiroFlag === 1;
    }

    public function addSelectColumns(
        array $select,
        ?string $tableAlias = null,
        string $fechaAlias = 'fecha_retiro',
        string $flagAlias = 'retiro_flag',
    ): array {
        $fechaRetiroColumn = $this->getFechaRetiroColumn();
        if ($fechaRetiroColumn !== null) {
            $qualified = $tableAlias ? "{$tableAlias}.{$fechaRetiroColumn}" : $fechaRetiroColumn;
            $select[] = "{$qualified} as {$fechaAlias}";
        }

        $retiroFlagColumn = $this->getRetiroFlagColumn();
        if ($retiroFlagColumn !== null) {
            $qualified = $tableAlias ? "{$tableAlias}.{$retiroFlagColumn}" : $retiroFlagColumn;
            $select[] = "{$qualified} as {$flagAlias}";
        }

        return $select;
    }

    public function applyNoRetiradosConstraint(Builder $query, ?string $tableAlias = null): void
    {
        $fechaRetiroColumn = $this->getFechaRetiroColumn();
        $retiroFlagColumn = $this->getRetiroFlagColumn();

        if ($fechaRetiroColumn === null && $retiroFlagColumn === null) {
            return;
        }

        if ($fechaRetiroColumn !== null) {
            $query->where(function (Builder $subquery) use ($tableAlias, $fechaRetiroColumn): void {
                $subquery
                    ->whereNull($this->qualifyColumn($fechaRetiroColumn, $tableAlias))
                    ->orWhereRaw("TRIM(COALESCE({$this->qualifyColumn($fechaRetiroColumn, $tableAlias)}, '')) = ''");
            });
        }

        if ($retiroFlagColumn !== null) {
            $query->whereRaw("COALESCE({$this->qualifyColumn($retiroFlagColumn, $tableAlias)}, 0) <> 1");
        }
    }

    private function firstAvailableValue(object $cliente, array $properties): mixed
    {
        foreach ($properties as $property) {
            if (!property_exists($cliente, $property)) {
                continue;
            }

            return $cliente->{$property};
        }

        return null;
    }

    private function getFechaRetiroColumn(): ?string
    {
        if ($this->fechaRetiroResolved) {
            return $this->fechaRetiroColumn;
        }

        $this->fechaRetiroResolved = true;
        $this->fechaRetiroColumn = Schema::hasColumn('clientes_potenciales', 'fecha_retiro')
            ? 'fecha_retiro'
            : null;

        return $this->fechaRetiroColumn;
    }

    private function getRetiroFlagColumn(): ?string
    {
        if ($this->retiroFlagResolved) {
            return $this->retiroFlagColumn;
        }

        $this->retiroFlagResolved = true;

        foreach (['retiro', 'retirado'] as $column) {
            if (Schema::hasColumn('clientes_potenciales', $column)) {
                $this->retiroFlagColumn = $column;
                break;
            }
        }

        return $this->retiroFlagColumn;
    }

    private function qualifyColumn(string $column, ?string $tableAlias = null): string
    {
        $alias = trim((string) $tableAlias);

        return $alias !== '' ? "{$alias}.{$column}" : $column;
    }
}
