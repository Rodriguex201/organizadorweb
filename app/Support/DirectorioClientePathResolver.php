<?php

namespace App\Support;

use App\Models\ConfiguracionDirectorio;
use Illuminate\Support\Facades\Schema;

class DirectorioClientePathResolver
{
    public static function resolve(): ?string
    {
        if (!Schema::hasTable('configuracion_directorio')) {
            return null;
        }

        $config = ConfiguracionDirectorio::query()->first();
        $ruta = trim((string) ($config?->ruta_clientes ?? ''));

        return $ruta !== '' ? $ruta : null;
    }
}
