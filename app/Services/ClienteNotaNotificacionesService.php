<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClienteNotaNotificacionesService
{
    private function elegibles(int $usuarioId): Builder
    {
        abort_unless($usuarioId > 0, 401);

        return DB::table('cliente_notas as n')
            ->whereNull('n.deleted_at')
            ->whereNotNull('n.creado_por')
            ->where('n.creado_por', '<>', $usuarioId)
            ->whereIn('n.tipo', ['GENERAL', 'CHECKLIST']);
    }

    public function pendientes(int $usuarioId, int $pagina = 1): array
    {
        $query = $this->elegibles($usuarioId)
            ->leftJoin('cliente_nota_lecturas as l', function ($join) use ($usuarioId): void {
                $join->on('l.nota_id', '=', 'n.id')->where('l.usuario_id', '=', $usuarioId);
            })->whereNull('l.nota_id');

        $total = (clone $query)->count();
        $paginas = max(1, (int) ceil($total / 20));
        $pagina = max(1, min($pagina, $paginas));
        $notas = $query
            ->leftJoin('usuarios as u', 'u.idusuario', '=', 'n.creado_por')
            ->leftJoin('clientes_potenciales as c', 'c.idclientes_potenciales', '=', 'n.cliente_id')
            ->orderByDesc('n.created_at')->orderByDesc('n.id')
            ->offset(($pagina - 1) * 20)->limit(20)
            ->get(['n.id', 'n.tipo', 'n.texto', 'n.created_at', 'u.nombre as autor', 'c.nombre as cliente', 'c.empresa']);

        return [
            'no_leidas' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'notificaciones' => $notas->map(fn ($nota) => [
                'id' => (string) $nota->id,
                'autor' => $nota->autor ?: 'Usuario no disponible',
                'cliente' => trim((string) $nota->empresa) ?: ($nota->cliente ?: 'Cliente no disponible'),
                'tipo' => $nota->tipo,
                'resumen' => Str::limit(preg_replace('/\s+/u', ' ', trim($nota->texto)), 180),
                'fecha' => Carbon::parse($nota->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    public function marcarLeida(int $usuarioId, string $notaId): void
    {
        DB::transaction(function () use ($usuarioId, $notaId): void {
            // La lectura pertenece exclusivamente a la sesión. No cambia la nota.
            $nota = $this->elegibles($usuarioId)->where('n.id', $notaId)->lockForUpdate()->first(['n.id']);
            abort_unless($nota, 404, 'Notificación no disponible.');
            DB::table('cliente_nota_lecturas')->insertOrIgnore([
                'nota_id' => $nota->id,
                'usuario_id' => $usuarioId,
                'leida_en' => now(),
            ]);
        });
    }
}
