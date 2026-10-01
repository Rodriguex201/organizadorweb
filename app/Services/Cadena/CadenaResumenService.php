<?php

namespace App\Services\Cadena;

class CadenaResumenService
{
    public function __construct(private readonly CadenaValidacionService $validation) {}

    /** Pure preparation: no database, session, filesystem persistence or commercial writes. */
    public function build(array $files, array $clients, array $destinations): array
    {
        $groups = $audit = $sources = $hashes = $documents = [];
        $scope = array_fill_keys(['facturas', 'soporte', 'eventos'], 'CONSERVAR');
        foreach ($files as $file) {
            $origin = ['categoria' => $file['categoria'], 'hash' => $file['hash']];
            $source = ['archivo' => $file['archivo'], 'categoria' => $file['categoria'], 'hash' => $file['hash'], 'estado' => 'Leído'];
            // One workbook may legitimately supply support and events in the same preview.
            $fileKey = $file['categoria'].'|'.$file['hash'];
            if (isset($hashes[$fileKey])) {
                $sources[] = $source + ['detalle' => 'Archivo duplicado de '.$hashes[$fileKey]];
                $sources[array_key_last($sources)]['estado'] = 'Duplicado excluido';
                continue;
            }
            $hashes[$fileKey] = $file['archivo'];
            if (isset($file['error'])) {
                $sources[] = array_replace($source, ['estado' => 'Error', 'detalle' => $file['error']]);
                $audit[] = $origin + ['archivo' => $file['archivo'], 'hoja' => '', 'fila' => '', 'estado' => 'Error', 'detalle' => $file['error']];
                continue;
            }
            $scope[$file['categoria']] = 'PARCIAL — solo cantidades observadas; ausentes: CONSERVAR';
            $sources[] = $source + ['detalle' => 'Hojas: '.implode(', ', array_column($file['hojas'], 'hoja'))];
            foreach ($file['ignoradas'] ?? [] as $ignored) {
                $audit[] = $origin + ['archivo' => $file['archivo'], 'hoja' => $ignored, 'fila' => '', 'estado' => 'Excluida', 'detalle' => 'Hoja sin encabezados de la categoría seleccionada.'];
            }
            foreach ($file['hojas'] as $sheet) {
                foreach ($sheet['filas'] as $row) {
                    $trace = $origin + ['archivo' => $file['archivo'], 'hoja' => $sheet['hoja'], 'fila' => $row['fila']];
                    $values = $row['valores'];
                    try {
                        foreach ($values as $value) {
                            if (str_starts_with($value, '=')) {
                                throw new \InvalidArgumentException('La fila contiene fórmulas; exporta valores originales.');
                            }
                        }
                        $event = $file['categoria'] === 'eventos';
                        $nit = $this->validation->nit($values[$event ? 'receptor' : 'nit'] ?? '', $values[$event ? 'dv_receptor' : 'dv'] ?? null);
                        if ($event) {
                            $counts = $this->validation->eventos($values['evento'], $values['cantidad'] ?? null);
                        } else {
                            $type = ctype_digit($values['tipo']) ? str_pad($values['tipo'], 2, '0', STR_PAD_LEFT) : '';
                            $allowed = $file['categoria'] === 'soporte' ? ['05', '95'] : ['01', '91', '92'];
                            if (!in_array($type, $allowed, true)) {
                                $audit[] = $trace + ['estado' => 'Excluida', 'detalle' => 'Tipo '.$values['tipo'].' fuera de la categoría '.$file['categoria'].($type === '05' || $type === '95' ? ' Cárgalo en Documento soporte.' : '')];
                                continue;
                            }
                            $counts = [$type => 1];
                        }
                        $key = $nit['nit_base'].'|'.$nit['dv'];
                        $match = $this->validation->match($nit, $clients);
                        $groups[$key] ??= $nit + $match + ['cantidades' => [], 'destinos' => $match['cliente_id'] === null ? [] : ($destinations[$match['cliente_id']] ?? [])];
                        $identity = null;
                        foreach ($counts as $code => $quantity) {
                            if ($event && ($values['evento_id'] ?? '') !== '') {
                                // Deduplicate each code even if one export combines codes and another splits them.
                                $identity = json_encode(['evento', $nit['nit_base'], $values['evento_id'], $code]);
                            } elseif (!$event && array_key_exists('prefijo', $values) && ($values['numero'] ?? '') !== '') {
                                $identity = json_encode(['documento', $nit['nit_base'], $code, mb_strtoupper($values['prefijo']), mb_strtoupper($values['numero'])]);
                            }
                            if ($identity !== null && isset($documents[$identity])) {
                                $first = $documents[$identity];
                                $conflict = $first['cantidad'] !== $quantity || $first['dv'] !== $nit['dv'];
                                $audit[] = $trace + ['estado' => $conflict ? 'Error' : 'Duplicado', 'detalle' => 'Código '.$code.'. '.($conflict ? 'Identidad repetida con DV o cantidades contradictorias. Se conserva solo la primera para revisión: ' : 'No contado; primera aparición: ').$first['origen']];
                                unset($counts[$code]);
                                continue;
                            }
                            if ($identity !== null) {
                                $documents[$identity] = ['cantidad' => $quantity, 'dv' => $nit['dv'], 'origen' => $file['archivo'].' / '.$sheet['hoja'].' / '.$row['fila']];
                            }
                        }
                        if ($counts === []) {
                            continue;
                        }
                        foreach ($counts as $code => $quantity) {
                            $groups[$key]['cantidades'][$code] = ($groups[$key]['cantidades'][$code] ?? 0) + $quantity;
                        }
                        $detail = implode(', ', array_map(fn ($code, $count) => $code.': '.$count, array_keys($counts), $counts));
                        $detail .= $identity === null ? '. Sin identidad suficiente: deduplicación documental no verificable.' : '. Identidad documental verificada dentro de esta carga.';
                        if (!$nit['dv_valido']) {
                            $detail .= ' DV informado no coincide con el calculado.';
                        }
                        $audit[] = $trace + ['estado' => $match['cliente_id'] === null ? $match['estado'] : 'Contada', 'detalle' => $nit['nit_base'].'-'.$nit['dv'].' — '.$detail];
                    } catch (\InvalidArgumentException $exception) {
                        $audit[] = $trace + ['estado' => 'Error', 'detalle' => $exception->getMessage()];
                    }
                }
            }
        }

        return ['clientes' => array_values($groups), 'archivos' => $sources, 'auditoria' => $audit, 'alcance' => $scope];
    }
}
