<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Propiedad extends Model
{
    protected $table = 'propiedad';
    protected $primaryKey = 'idpropiedad';
    public $timestamps = false;

    public const DISPONIBLE = 'DISPONIBLE';
    public const RESERVADO = 'RESERVADO';
    public const VENDIDO = 'VENDIDO';
    public const ALQUILADO = 'ALQUILADO';
    public const BAJA = 'BAJA'; // baja lógica (CU7)

    public const ESTADOS = [self::DISPONIBLE, self::RESERVADO, self::VENDIDO, self::ALQUILADO, self::BAJA];
    public const TIPOS = ['Venta', 'Alquiler', 'Anticrético'];

    protected $fillable = [
        'idpropietario', 'idcategoria', 'idubicacion', 'idusuario',
        'titulo', 'descripcion', 'precio', 'tipopropiedad', 'estadopropiedad',
        'superficie', 'areaconstruida', 'habitaciones', 'banos', 'antiguedad',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'fecharegistro' => 'datetime',
        ];
    }

    /** Propiedades que no están dadas de baja (las que se ven en el catálogo). */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estadopropiedad', '!=', self::BAJA);
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'idpropietario', 'idpropietario');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'idcategoria', 'idcategoria');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'idubicacion', 'idubicacion');
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(Imagen::class, 'idpropiedad', 'idpropiedad');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(Historial::class, 'idpropiedad', 'idpropiedad');
    }
}
