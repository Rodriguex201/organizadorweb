<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteNota extends Model
{
    use SoftDeletes;

    protected $table = 'cliente_notas';

    protected $fillable = ['tipo', 'texto', 'monto', 'fecha_objetivo'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_objetivo' => 'date:Y-m-d',
        'completada' => 'boolean',
        'completada_en' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(ClientePotencial::class, 'cliente_id', 'idclientes_potenciales');
    }
}
