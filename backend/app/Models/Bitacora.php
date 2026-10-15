<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacora';
    protected $primaryKey = 'idbitacora';
    public $timestamps = false;

    protected $fillable = ['idusuario', 'accion', 'modulo', 'descripcion', 'detalle', 'ip', 'fecha'];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }
}
