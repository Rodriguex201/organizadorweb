<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usuario extends Model
{
    protected $table = 'usuarios';

    protected $primaryKey = 'idusuario';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'contrasena',
        'roles_idroles',
        'estado',
    ];

    protected $hidden = [
        'contrasena',
    ];

    protected function casts(): array
    {
        return [
            'roles_idroles' => 'integer',
            'estado' => 'integer',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'roles_idroles', 'idroles');
    }
}
