<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'idroles';

    public $timestamps = false;

    protected $fillable = [
        'rol',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'roles_idroles', 'idroles');
    }
}
