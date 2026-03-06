<?php

namespace App\DataTransferObjects;

class LineaProforma
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $concepto,
        public readonly float $cantidad,
        public readonly float $valorUnitario,
        public readonly ?float $valorParcialOverride = null,
    ) {
    }

    public function valorParcial(): float
    {
        if ($this->valorParcialOverride !== null) {
            return $this->valorParcialOverride;
        }

        return $this->cantidad * $this->valorUnitario;
    }

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'concepto' => $this->concepto,
            'cantidad' => $this->cantidad,
            'valor_unitario' => $this->valorUnitario,
            'valor_parcial' => $this->valorParcial(),
        ];
    }
}
