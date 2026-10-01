<?php

namespace App\Services\Cadena;

/** Read-only assessment of the existing prepared result; never recomputes quantities. */
class CadenaGeneracionService
{
    public const FILES = ['facturas' => 'ResumenCombinado.xlsx', 'soporte' => 'ResumenDocumentoSoporte.xlsx', 'eventos' => 'ResumenEventos.xlsx'];

    public function issues(array $preview, ?array $exports, string $action): array
    {
        $issues = [];
        if (!($preview['consulta_disponible'] ?? false)) {
            $issues[] = 'No fue posible verificar clientes y destinos. Vuelve a intentar cuando la consulta esté disponible.';
        }
        foreach ($preview['archivos'] as $file) {
            if ($file['estado'] === 'Error') { $issues[] = $file['categoria'].' / '.$file['archivo'].': '.$file['detalle']; }
        }
        foreach ($preview['auditoria'] as $entry) {
            if ($entry['estado'] === 'Error') {
                $issues[] = ($entry['categoria'] ?? '').' / '.$entry['archivo'].' / '.$entry['hoja'].' / fila '.$entry['fila'].': '.$entry['detalle'];
            }
        }
        foreach ($preview['clientes'] as $client) {
            $codes = array_keys($client['cantidades'] ?? []);
            $supportOnly = $codes !== [] && array_diff($codes, ['05', '95']) === [];
            if (!$client['dv_valido'] && !$supportOnly) {
                $issues[] = 'NIT '.$client['nit_base'].'-'.$client['dv'].': '.$client['estado'].'. Pendiente de revisión; no se asigna automáticamente.';
            }
        }
        foreach ($exports['omitidos'] ?? [] as $omitted) {
            // Soporte pendiente queda en auditoría; no bloquea los clientes válidos.
            if ($omitted[0] !== 'soporte') { $issues[] = implode(' · ', $omitted); }
        }
        $wanted = $action === 'paquete' ? self::FILES : [$action => self::FILES[$action]];
        foreach ($wanted as $category => $filename) {
            if (!isset($exports['tables'][$filename])) {
                $issues[] = $category.': '.($exports['errores'][$filename] ?? 'No se pudo preparar '.$filename.'.');
            } elseif ($exports['tables'][$filename]['rows'] === []) {
                $issues[] = $category.': no hay registros exportables; revisa encabezados, tipos y clientes.';
            }
        }
        if ($action === 'paquete') {
            foreach ($preview['descargas']['paquete_errores'] ?? ['No se pudo preparar el paquete.'] as $error) { $issues[] = $error; }
        }
        return array_values(array_unique($issues));
    }

    public function pendingCount(array $preview): int
    {
        return count(array_filter($preview['clientes'], fn (array $client) => $client['cliente_id'] === null && $client['dv_valido']));
    }
}
