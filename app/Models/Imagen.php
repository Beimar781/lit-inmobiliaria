<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Imagen extends Model
{
    protected $table = 'imagen';
    protected $primaryKey = 'idimagen';
    public $timestamps = false;

    protected $fillable = ['idpropiedad', 'nombre', 'ruta'];

    protected function casts(): array
    {
        return ['fechacarga' => 'datetime'];
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }
}
