<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Historial extends Model
{
    protected $table = 'historial';
    protected $primaryKey = 'idhistorial';
    public $timestamps = false;

    public const REGISTRO = 'REGISTRO';
    public const MODIFICACION = 'MODIFICACION';
    public const BAJA = 'BAJA';

    protected $fillable = ['tipo', 'valoranterior', 'valoractual', 'motivo', 'idusuario', 'idpropiedad'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }
}
