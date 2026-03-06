<?php

namespace App\DataTransferObjects;

class LineaProforma
{
    public function __construct(
        public readonly string $concepto,
        public readonly float $cantidad,
        public readonly float $valorUnitario,
    ) {
    }

    public function valorParcial(): float
    {
        return $this->cantidad * $this->valorUnitario;
    }

    public function toArray(): array
    {
        return [
            'concepto' => $this->concepto,
            'cantidad' => $this->cantidad,
            'valor_unitario' => $this->valorUnitario,
            'valor_parcial' => $this->valorParcial(),
        ];
    }
}
