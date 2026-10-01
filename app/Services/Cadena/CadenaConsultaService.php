<?php

namespace App\Services\Cadena;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only adapter. Never call the legacy import or proforma generation services here. */
class CadenaConsultaService
{
    public function __construct(private readonly CadenaValidacionService $validation) {}

    public function snapshot(string $month, int $year, int $monthNumber): array
    {
        $clients = [];
        $warnings = [];
        foreach (DB::table('clientes_potenciales')->select('idclientes_potenciales', 'nit', 'dv', 'empresa', 'nombre', 'codigo', 'regimen')->get() as $row) {
            try {
                $clients[] = $this->validation->nit($row->nit, $row->dv) + [
                    'cliente_id' => (int) $row->idclientes_potenciales,
                    'nombre' => trim((string) ($row->empresa ?: $row->nombre)),
                    'codigo' => (string) $row->codigo,
                    'emisora' => in_array(strtoupper(trim((string) $row->regimen)), ['PCS', 'SMP'], true) ? strtoupper(trim((string) $row->regimen)) : 'SAS',
                ];
            } catch (\InvalidArgumentException $exception) {
                $warnings[] = 'Cliente #'.$row->idclientes_potenciales.': '.$exception->getMessage();
            }
        }
        $yearColumn = Schema::hasColumn('valores_externos', 'año') ? 'año' : 'aÃ±o';
        $charges = DB::table('valores_externos')->whereRaw('LOWER(TRIM(mes)) = ?', [$month])->where($yearColumn, $year)->get();
        $proformas = DB::table('sg_proform')->where('mes', $monthNumber)->where('anio', $year)->get();
        $destinations = [];
        $clientsById = array_column($clients, null, 'cliente_id');
        foreach ($charges as $charge) {
            $client = $clientsById[$charge->id_cliente] ?? null;
            if ($client === null) {
                continue;
            }
            $matches = [];
            foreach ($proformas as $proforma) {
                $linked = (int) ($proforma->id_cobro ?? 0);
                $legacy = false;
                if ($linked > 0) {
                    if ($linked !== (int) $charge->id_cobro) {
                        continue;
                    }
                } else {
                    try {
                        $nit = $this->validation->nit($proforma->nit);
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                    if ($nit['nit_base'] !== $client['nit_base'] || strtoupper(trim((string) $proforma->emisora)) !== $client['emisora']) {
                        continue;
                    }
                    $legacy = true;
                }
                $matches[] = ['id' => $proforma->id, 'protecciones' => $this->validation->protecciones((array) $proforma), 'legacy' => $legacy];
            }
            $destinations[$client['cliente_id']][] = ['id_cobro' => $charge->id_cobro, 'proformas' => $matches,
                'protecciones' => $this->validation->protecciones((array) $charge)];
        }

        return ['clientes' => $clients, 'destinos' => $destinations, 'advertencias' => $warnings];
    }
}
