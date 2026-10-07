<?php

namespace App\Services;

use App\Models\ClientePotencial;
use App\Models\ClienteProformaWhatsapp;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteProformaWhatsappService
{
    public static function reglas(): array
    {
        return [
            'grupo_fecha' => ['required', 'integer', 'in:7,27'],
            'telefono_fuente' => ['required', 'in:CELULAR1,CELULAR2,ALTERNATIVO'],
            'whatsapp_alternativo' => ['nullable', 'required_if:telefono_fuente,ALTERNATIVO', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ];
    }

    public static function normalizarAlternativo(?string $numero): ?string
    {
        $numero = preg_replace('/[\s().-]+/', '', trim($numero ?? ''));
        return $numero === '' ? null : $numero;
    }

    public static function telefono(object $cliente, string $fuente, ?string $alternativo): array
    {
        $raw = match ($fuente) {
            'CELULAR1' => $cliente->celular1 ?? '',
            'CELULAR2' => $cliente->celular2 ?? '',
            default => $alternativo ?? '',
        };
        $numero = self::normalizarAlternativo((string) $raw) ?? '';
        // Los celulares colombianos locales se muestran con su prefijo internacional.
        if (preg_match('/^3[0-9]{9}$/', $numero)) $numero = '+57'.$numero;
        if (preg_match('/^573[0-9]{9}$/', $numero)) $numero = '+'.$numero;
        return ['whatsapp' => $numero ?: 'Sin número', 'telefono_valido' => (bool) preg_match('/^\+[1-9][0-9]{7,14}$/', $numero)];
    }

    public function buscar(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return [];
        $pattern = '%'.mb_strtolower($term).'%';
        return ClientePotencial::query()->with('proformaWhatsapp')
            ->where(function ($query) use ($pattern): void {
                $query->whereRaw('LOWER(codigo) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(empresa) LIKE ?', [$pattern]);
            })->orderBy('codigo')->orderBy('idclientes_potenciales')->limit(20)
            ->get(['idclientes_potenciales', 'codigo', 'nombre', 'empresa', 'nit', 'celular1', 'celular2'])
            ->map(fn ($cliente) => $this->clienteData($cliente) + [
                'configurado' => $cliente->proformaWhatsapp !== null,
                'activo' => $cliente->proformaWhatsapp?->activo,
            ])->all();
    }

    private function clienteData(object $cliente): array
    {
        return [
            'cliente_id' => (int) $cliente->idclientes_potenciales,
            'codigo' => trim((string) $cliente->codigo),
            'empresa' => trim((string) $cliente->empresa) ?: trim((string) $cliente->nombre),
            'nit' => trim((string) $cliente->nit),
            'celular1' => trim((string) $cliente->celular1),
            'celular2' => trim((string) $cliente->celular2),
        ];
    }

    public function listado(?int $grupo, int $pagina): array
    {
        $query = ClienteProformaWhatsapp::query()->with('cliente');
        if ($grupo !== null) $query->where('grupo_fecha', $grupo);
        $total = (clone $query)->count();
        $paginas = max(1, (int) ceil($total / 50));
        $pagina = max(1, min($pagina, $paginas));
        $items = $query->orderBy('cliente_id')->offset(($pagina - 1) * 50)->limit(50)->get();
        return ['pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'data' => $items->map(function ($item) {
            $cliente = $item->cliente;
            return $this->clienteData($cliente) + [
                'grupo_fecha' => $item->grupo_fecha, 'activo' => $item->activo,
                'telefono_fuente' => $item->telefono_fuente,
                'whatsapp_alternativo' => $item->whatsapp_alternativo,
            ] + self::telefono($cliente, $item->telefono_fuente, $item->whatsapp_alternativo);
        })->all()];
    }

    public function agregar(int $clienteId, array $data): void
    {
        DB::transaction(function () use ($clienteId, $data): void {
            // Serializa altas del mismo cliente; la PK también impide duplicados.
            $cliente = ClientePotencial::whereKey($clienteId)->lockForUpdate()->firstOrFail();
            abort_if(ClienteProformaWhatsapp::whereKey($clienteId)->exists(), 409, 'El cliente ya está configurado. Edítelo desde la lista.');
            $this->validarFuente($cliente, $data);
            $item = new ClienteProformaWhatsapp($data);
            $item->cliente_id = $clienteId;
            $item->activo = true;
            $item->save();
        });
    }

    public function editar(int $clienteId, array $data): void
    {
        $this->validarFuente(ClientePotencial::findOrFail($clienteId), $data);
        ClienteProformaWhatsapp::findOrFail($clienteId)->fill($data)->save();
    }

    public function validarFuente(object $cliente, array $data): void
    {
        $source = $data['telefono_fuente'];
        if (in_array($source, ['CELULAR1', 'CELULAR2'], true)
            && trim((string) ($cliente->{strtolower($source)} ?? '')) === '') {
            throw ValidationException::withMessages(['telefono_fuente' => 'Número pendiente: el celular seleccionado está vacío. Elige otra fuente.']);
        }
    }

    public function eliminar(int $clienteId): void
    {
        ClienteProformaWhatsapp::findOrFail($clienteId)->delete();
    }
}
