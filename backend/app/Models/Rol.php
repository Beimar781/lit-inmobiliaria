<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'rol';
    protected $primaryKey = 'idrol';
    public $timestamps = false;

    public const ADMINISTRADOR = 'Administrador';
    public const AGENTE = 'Agente Inmobiliario';
    public const ASISTENTE = 'Asistente Administrativo';
    public const CLIENTE = 'Cliente';

    protected $fillable = ['nombre', 'descripcion'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'idrol', 'idrol');
    }
}
