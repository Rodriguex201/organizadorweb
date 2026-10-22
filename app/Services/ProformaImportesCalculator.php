<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Pure decimal calculation shared by preview and persistence. */
final class ProformaImportesCalculator
{
    public function normalizeLineas(array $lineas): array
    {
        $lineas = array_values(array_filter($lineas, fn ($l) => ($l['codigo'] ?? '') !== '01'));
        foreach ($lineas as &$linea) {
            $partial = is_float($linea['valor_parcial'])
                ? json_encode($linea['valor_parcial'], JSON_THROW_ON_ERROR)
                : (string) $linea['valor_parcial'];
            $linea['valor_parcial'] = (string) BigDecimal::of($partial)->toScale(2, RoundingMode::HalfUp);
        }
        unset($linea);
        return $lineas;
    }

    public function calculate(array $lineas, string $porcentaje): array
    {
        $rate = BigDecimal::of($porcentaje)->toScale(3);
        if ($rate->isLessThan(0) || $rate->isGreaterThan(100)) {
            throw new InvalidArgumentException('Porcentaje de retención fuera de rango.');
        }
        $original = BigDecimal::zero();
        foreach ($lineas as $linea) {
            $original = $original->plus((string) $linea['valor_parcial']);
        }
        $original = $original->toScale(2, RoundingMode::HalfUp);
        if ($original->isLessThan(0) || $original->isGreaterThan('9999999999999.99')) {
            throw new InvalidArgumentException('Valor de proforma fuera de rango.');
        }
        $retencion = $original->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);
        return [
            'valor_original' => (string) $original,
            'retencion_porcentaje' => (string) $rate,
            'retencion_valor' => (string) $retencion,
            'vtotal' => (string) $original->minus($retencion),
        ];
    }
}
