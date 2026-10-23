<?php
namespace App\Payments\Bold;

use RuntimeException;

final class ActivationRuleResolver
{
    public function __construct(private array $config)
    {
        if (($config['remote_enabled'] ?? null) !== false
            || ($config['rules_version'] ?? '') !== 'SIN_REGLAS_APROBADAS'
            || ($config['rules'] ?? null) !== []) {
            throw new RuntimeException('Solo planificación sin reglas aprobadas ni transporte remoto.');
        }
    }

    public function resolve(array $lines, array $knownCodes, ?string $company, ?string $contextError = null): array
    {
        $origins = [];
        foreach ($lines as $line) {
            $code = (string) ($line['ref_codigo'] ?? '');
            $known = in_array($code, $knownCodes, true);
            $origins[] = ['linea_id'=>(int) $line['id'], 'ref_codigo'=>$line['ref_codigo'],
                'conocido'=>$known, 'motivo'=>trim($code)==='' ? 'CONCEPTO_SIN_CODIGO' : ($known ? 'REGLA_NO_APROBADA' : 'CONCEPTO_DESCONOCIDO')];
        }
        $company = strtoupper(trim($company ?? ''));
        $validCompany = preg_match('/\A[A-Z0-9]{1,100}\z/', $company) === 1;
        $error = $contextError ?? (!$validCompany ? 'CLIENTE_SIN_CODIGO' : ($lines === [] ? 'PROFORMA_SIN_CONCEPTOS' : 'REGLA_NO_APROBADA'));
        if ($contextError === null && $validCompany && $lines !== []) {
            foreach ($origins as $origin) if ($origin['motivo'] !== 'REGLA_NO_APROBADA') { $error = $origin['motivo']; break; }
        }
        return ['servicio'=>'PLANIFICACION', 'estado'=>'REVISION', 'empresa_codigo'=>$validCompany ? $company : null,
            'regla_version'=>$this->config['rules_version'], 'conceptos_origen'=>$origins, 'error_codigo'=>$error];
    }
}
