<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteProformaWhatsapp extends Model
{
    protected $table = 'cliente_proforma_whatsapp';
    protected $primaryKey = 'cliente_id';
    public $incrementing = false;
    protected $fillable = ['grupo_fecha', 'telefono_fuente', 'whatsapp_alternativo'];
    protected $casts = ['activo' => 'boolean', 'grupo_fecha' => 'integer'];

    public function cliente(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClientePotencial::class, 'cliente_id', 'idclientes_potenciales');
    }
}
