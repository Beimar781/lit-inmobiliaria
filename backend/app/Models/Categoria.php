<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categoria';
    protected $primaryKey = 'idcategoria';
    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion'];

    public function propiedades(): HasMany
    {
        return $this->hasMany(Propiedad::class, 'idcategoria', 'idcategoria');
    }
}
