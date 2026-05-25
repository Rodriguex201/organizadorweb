<?php

namespace App\Services;

class ClienteValorTotalCalculator
{
    public function calculate(array $input): float
    {
        $valorPrincipal = $this->toFloat($input['vlrprincipal'] ?? $input['valor_principal'] ?? 0);
        $numeroEquipos = $this->toFloat($input['numequipos'] ?? $input['numero_equipos'] ?? 0);
        $valorTerminal = $this->toFloat($input['vlrterminal'] ?? $input['valor_terminal'] ?? 0);
        $numeroEquiposExtra = $this->toFloat($input['numextra'] ?? $input['numero_equipos_extra'] ?? 0);
        $valorEquipoExtra = $this->toFloat($input['vlrextrae'] ?? $input['valor_equipo_extra'] ?? 0);
        $valorExtra = $this->toFloat($input['vlrextra'] ?? $input['otro_valor_extra'] ?? 0);
        $valorExtra2 = $this->toFloat($input['vlrextra2'] ?? $input['otro_valor_extra_2'] ?? $input['valor_terminal_recepcion'] ?? 0);
        $valorNomina = $this->toFloat($input['vlrnomina'] ?? $input['valor_nomina'] ?? 0);
        $numeroMoviles = $this->toFloat($input['numeromoviles'] ?? $input['numero_moviles'] ?? 0);
        $valorMovil = $this->toFloat($input['vlrmovil'] ?? $input['valor_movil'] ?? 0);

        $equiposAdicionales = max($numeroEquipos - 1, 0);

        return $valorPrincipal
            + ($valorTerminal * $equiposAdicionales)
            + ($valorEquipoExtra * $numeroEquiposExtra)
            + $valorExtra
            + $valorExtra2
            + $valorNomina
            + ($valorMovil * $numeroMoviles);
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
