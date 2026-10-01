<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'ubicacion';
    protected $primaryKey = 'idubicacion';
    public $timestamps = false;

    protected $fillable = ['ciudad', 'zona', 'direccion', 'latitud', 'longitud'];

    public function propiedades(): HasMany
    {
        return $this->hasMany(Propiedad::class, 'idubicacion', 'idubicacion');
    }
}
