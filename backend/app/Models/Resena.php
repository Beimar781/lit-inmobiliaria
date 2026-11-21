<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reseña de un cliente sobre una propiedad (CU25), con puntuación de 1 a 5. */
class Resena extends Model
{
    protected $table = 'resena';
    protected $primaryKey = 'idresena';
    public $timestamps = false;

    protected $fillable = ['idpropiedad', 'idcliente', 'puntuacion', 'comentario', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }
}
