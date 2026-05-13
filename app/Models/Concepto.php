<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concepto extends Model
{
    protected $table = 'conceptos';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'cuenta',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
