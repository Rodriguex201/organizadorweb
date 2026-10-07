<?php

namespace App\Services;

use App\Models\ClienteNota;
use App\Models\ClientePotencial;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteNotasService
{
    /** El contexto cifrado vincula el botón con su fila de origen. */
    public static function contexto(string $origen, int $id, int $clienteId): string
    {
        return Crypt::encryptString(json_encode([
            'origen' => $origen, 'id' => $id, 'cliente_id' => $clienteId,
        ], JSON_THROW_ON_ERROR));
    }

    public function verificarContexto(int $clienteId, ?string $contexto): ClientePotencial
    {
        try {
            $data = json_decode(Crypt::decryptString($contexto ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            abort(422, 'Abra de nuevo las notas desde el listado para validar el cliente.');
        }

        abort_unless(is_array($data) && (int) ($data['cliente_id'] ?? 0) === $clienteId
            && (int) ($data['id'] ?? 0) > 0, 422, 'Contexto de cliente inválido.');

        $ids = match ($data['origen'] ?? '') {
            'proforma' => app(ProformasService::class)->clientesParaNotas((int) $data['id']),
            'cobro' => DB::table('valores_externos as ve')
                ->join('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', DB::raw('CAST(TRIM(ve.id_cliente) AS UNSIGNED)'))
                ->where('ve.id_cobro', (int) $data['id'])
                ->distinct()->pluck('cp.idclientes_potenciales')->map(fn ($id) => (int) $id)->all(),
            default => [],
        };

        abort_unless(count($ids) === 1 && $ids[0] === $clienteId, 409,
            'No se pueden modificar notas: la relación con el cliente es ambigua o ya no está disponible. Recargue el listado.');

        return ClientePotencial::findOrFail($clienteId);
    }

    public function listado(int $clienteId): array
    {
        $cliente = ClientePotencial::findOrFail($clienteId);
        $notas = ClienteNota::where('cliente_id', $clienteId)
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return [
            'cliente_id' => $clienteId,
            'nota_cobro' => $cliente->nota_cobro,
            'notas' => $notas->map(fn (ClienteNota $nota) => [
                'id' => (string) $nota->id,
                'tipo' => $nota->tipo,
                'texto' => $nota->texto,
                'monto' => $nota->monto,
                'fecha_objetivo' => $nota->fecha_objetivo?->format('Y-m-d'),
                'completada' => $nota->completada,
                'completada_en' => $nota->completada_en?->format('d/m/Y H:i'),
                'created_at' => $nota->created_at->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    public function crear(int $clienteId, array $data): void
    {
        $nota = new ClienteNota($data);
        $nota->cliente_id = $clienteId;
        $nota->completada = false;
        $nota->save();
    }

    public function modificar(int $clienteId, string $notaId, array $data): void
    {
        DB::transaction(function () use ($clienteId, $notaId, $data): void {
            $nota = $this->buscar($clienteId, $notaId);
            if ($data['tipo'] !== $nota->tipo) {
                throw ValidationException::withMessages(['tipo' => 'No se puede cambiar el tipo de una nota existente.']);
            }
            $nota->fill($data)->save();
        });
    }

    public function completar(int $clienteId, string $notaId, bool $completada): void
    {
        DB::transaction(function () use ($clienteId, $notaId, $completada): void {
            $nota = $this->buscar($clienteId, $notaId);
            abort_unless($nota->tipo === 'CHECKLIST', 422, 'Solo las tareas pueden completarse.');
            if ($nota->completada !== $completada) {
                $nota->completada = $completada;
                $nota->completada_en = $completada ? now() : null;
                $nota->save();
            }
        });
    }

    public function eliminar(int $clienteId, string $notaId): void
    {
        DB::transaction(fn () => $this->buscar($clienteId, $notaId)->delete());
    }

    private function buscar(int $clienteId, string $notaId): ClienteNota
    {
        return ClienteNota::where('cliente_id', $clienteId)->whereKey($notaId)->lockForUpdate()->firstOrFail();
    }

    public static function aplicarFiltro(Builder $query, string $filtro): void
    {
        if (!in_array($filtro, ['con', 'sin', 'pendientes'], true)) {
            return;
        }

        $nuevas = DB::table('cliente_notas as cn')->selectRaw('1')
            ->whereColumn('cn.cliente_id', 'cp.idclientes_potenciales')->whereNull('cn.deleted_at');

        if ($filtro === 'pendientes') {
            $query->whereExists($nuevas->where('cn.tipo', 'CHECKLIST')->where('cn.completada', 0));
        } elseif ($filtro === 'con') {
            $query->where(fn (Builder $q) => $q->whereRaw("TRIM(COALESCE(cp.nota_cobro, '')) <> ''")->orWhereExists($nuevas));
        } else {
            $query->whereRaw("TRIM(COALESCE(cp.nota_cobro, '')) = ''")->whereNotExists($nuevas);
        }
    }
}
