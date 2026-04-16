<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionDirectorio extends Model
{
    protected $table = 'configuracion_directorio';

    protected $fillable = [
        'ruta_clientes',
    ];
}
