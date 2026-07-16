<?php

namespace App\Services;

use App\Models\ClientePotencial;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Regla oficial de negocio para determinar la cartera facturable.
 *
 * Un cliente pertenece a la cartera facturable cuando no esta retirado y su
 * estado de facturacion corresponde al criterio activo usado por el sistema.
 */
class ClienteCarteraVigenteService
{
    public function __construct(
        private readonly ClienteRetiradoService $clienteRetiradoService,
    ) {
    }

    public function applyFacturablePortfolioConstraint(Builder $query, ?string $tableAlias = null): void
    {
        $this->clienteRetiradoService->applyNoRetiradosConstraint($query, $tableAlias);
        $this->applyFacturacionActivaConstraint($query, $tableAlias);
    }

    public function applyPortfolioConstraintAtDate(Builder $query, Carbon $cutoffDate, ?string $tableAlias = null): void
    {
        $this->applyFechaInicioConstraint($query, $cutoffDate, $tableAlias);
        $this->applyRetiroAfterCutoffConstraint($query, $cutoffDate, $tableAlias);
        $this->applyFacturacionActivaConstraint($query, $tableAlias);
    }

    private function applyFechaInicioConstraint(Builder $query, Carbon $cutoffDate, ?string $tableAlias = null): void
    {
        if (!Schema::hasColumn('clientes_potenciales', 'fecha_inicio_facturacion')) {
            return;
        }

        $query
            ->whereNotNull($this->qualifyColumn('fecha_inicio_facturacion', $tableAlias))
            ->whereRaw("TRIM(COALESCE({$this->qualifyColumn('fecha_inicio_facturacion', $tableAlias)}, '')) <> ''")
            ->whereDate($this->qualifyColumn('fecha_inicio_facturacion', $tableAlias), '<=', $cutoffDate->toDateString());
    }

    private function applyRetiroAfterCutoffConstraint(Builder $query, Carbon $cutoffDate, ?string $tableAlias = null): void
    {
        if (!Schema::hasColumn('clientes_potenciales', 'fecha_retiro')) {
            return;
        }

        $qualifiedColumn = $this->qualifyColumn('fecha_retiro', $tableAlias);

        $query->where(function (Builder $subquery) use ($qualifiedColumn, $cutoffDate): void {
            $subquery
                ->whereNull($qualifiedColumn)
                ->orWhereRaw("TRIM(COALESCE({$qualifiedColumn}, '')) = ''")
                ->orWhereDate($qualifiedColumn, '>', $cutoffDate->toDateString());
        });
    }

    private function applyFacturacionActivaConstraint(Builder $query, ?string $tableAlias = null): void
    {
        if (!Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
            return;
        }

        $query->whereRaw($this->billingStatusSql($this->qualifyColumn('estado_facturacion', $tableAlias)).' = ?', [
            ClientePotencial::ESTADO_FACTURACION_ACTIVO,
        ]);
    }

    private function billingStatusSql(string $column): string
    {
        return "CASE WHEN UPPER(TRIM(COALESCE({$column}, ''))) = 'PENDIENTE' THEN 'PENDIENTE' ELSE 'ACTIVO' END";
    }

    private function qualifyColumn(string $column, ?string $tableAlias = null): string
    {
        $alias = trim((string) $tableAlias);

        return $alias !== '' ? "{$alias}.{$column}" : $column;
    }
}
