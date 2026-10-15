<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Interés de un cliente por una propiedad (CU12). */
class Interes extends Model
{
    protected $table = 'interes';
    protected $primaryKey = 'idinteres';
    public $timestamps = false;

    protected $fillable = ['idpropiedad', 'idcliente', 'mensaje', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }
}
