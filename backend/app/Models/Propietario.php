<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Propietario extends Model
{
    protected $table = 'propietario';
    protected $primaryKey = 'idpropietario';
    public $timestamps = false;

    protected $fillable = ['nombre', 'telefono', 'email', 'direccion'];

    public function propiedades(): HasMany
    {
        return $this->hasMany(Propiedad::class, 'idpropietario', 'idpropietario');
    }
}
