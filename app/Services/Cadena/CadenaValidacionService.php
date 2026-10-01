<?php

namespace App\Services\Cadena;

use InvalidArgumentException;

class CadenaValidacionService
{
    public const DOCUMENTOS = ['01' => 'Facturas', '91' => 'Notas crédito', '92' => 'Notas débito', '05' => 'Documento soporte', '95' => 'Notas de ajuste'];
    public const EVENTOS = ['030' => 'Acuse de recibo de factura', '031' => 'Reclamo', '032' => 'Recibo del bien o servicio', '033' => 'Aceptación expresa', '034' => 'Aceptación tácita'];

    public function nit(mixed $value, mixed $dv = null): array
    {
        $raw = trim((string) $value);
        if (!preg_match('/^([0-9][0-9.\s]*)(?:\s*-\s*([0-9]))?$/D', $raw, $matches)) {
            throw new InvalidArgumentException('NIT vacío o inválido; no se aceptan letras ni notación científica.');
        }
        $base = ltrim(preg_replace('/[.\s]/', '', $matches[1]), '0');
        if ($base === '' || strlen($base) > 15) {
            throw new InvalidArgumentException('Longitud de NIT inválida.');
        }
        $explicit = trim((string) $dv);
        $inline = $matches[2] ?? '';
        if ($explicit !== '' && (!preg_match('/^[0-9]$/D', $explicit) || ($inline !== '' && $inline !== $explicit))) {
            throw new InvalidArgumentException('DV inválido o contradictorio.');
        }
        $provided = $explicit !== '' ? $explicit : $inline;
        $weights = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
        $sum = 0;
        foreach (str_split(str_pad($base, 15, '0', STR_PAD_LEFT)) as $i => $digit) {
            $sum += (int) $digit * $weights[$i];
        }
        $mod = $sum % 11;
        $calculated = (string) ($mod > 1 ? 11 - $mod : $mod);

        return ['nit_base' => $base, 'dv' => $provided === '' ? $calculated : $provided,
            'dv_informado' => $provided !== '', 'dv_valido' => $provided === '' || $provided === $calculated];
    }

    public function match(array $nit, array $clients): array
    {
        // An unseparated NIT is always a base, never silently stripped of its last digit.
        $candidates = array_values(array_filter($clients, fn (array $client) => $client['nit_base'] === $nit['nit_base']));
        $unique = count($candidates) === 1 && $nit['dv_valido'] && $candidates[0]['dv_valido']
            && $candidates[0]['dv'] === $nit['dv'];

        return ['cliente_id' => $unique ? $candidates[0]['cliente_id'] : null,
            'cliente' => $unique ? $candidates[0]['nombre'] : 'Pendiente de asignación',
            'candidatos' => $candidates,
            'estado' => $unique ? 'Reconocido' : (count($candidates) > 1 ? 'NIT ambiguo' : 'Pendiente de asignación')];
    }

    public function eventos(string $codes, ?string $transactions): array
    {
        $parts = preg_split('/(?:--|[,;\s])+/', trim($codes), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            throw new InvalidArgumentException('Falta el código de evento.');
        }
        $normalized = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part) || !isset(self::EVENTOS[str_pad($part, 3, '0', STR_PAD_LEFT)])) {
                throw new InvalidArgumentException('Código de evento desconocido: '.$part);
            }
            $normalized[] = str_pad($part, 3, '0', STR_PAD_LEFT);
        }
        if ($transactions !== null && (!preg_match('/^\d+$/D', $transactions) || strlen($transactions) > 9)) {
            throw new InvalidArgumentException('Cantidad de transacciones vacía, negativa o inválida.');
        }
        $count = $transactions === null ? count($normalized) : (int) $transactions;
        if (count($normalized) > 1 && $count !== count($normalized)) {
            throw new InvalidArgumentException('La lista de eventos no coincide con transactionsCount; no se distribuye una cantidad ambigua.');
        }
        $result = [];
        foreach ($normalized as $code) {
            $result[$code] = ($result[$code] ?? 0) + (count($normalized) === 1 ? $count : 1);
        }

        return $result;
    }

    public function protecciones(array $proforma): array
    {
        $flags = [];
        $state = (int) ($proforma['estado'] ?? 0);
        if ((int) ($proforma['enviado'] ?? 0) === 1 || $state === 3) {
            $flags[] = 'enviada';
        }
        if ($state === 4) {
            $flags[] = 'pagada';
        }
        if ($state === 6) {
            $flags[] = 'facturada';
        }

        return $flags;
    }
}
