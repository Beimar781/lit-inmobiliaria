<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Prospecto del CRM: persona que mostró interés en una propiedad (CU12) y a la que se da seguimiento (CU13). */
class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'idcliente';
    public $timestamps = false;

    public const NUEVO = 'NUEVO';
    public const CONTACTADO = 'CONTACTADO';
    public const EN_NEGOCIACION = 'EN_NEGOCIACION';
    public const CERRADO = 'CERRADO';
    public const DESCARTADO = 'DESCARTADO';

    public const ESTADOS = [
        self::NUEVO => 'Nuevo',
        self::CONTACTADO => 'Contactado',
        self::EN_NEGOCIACION => 'En negociación',
        self::CERRADO => 'Cerrado',
        self::DESCARTADO => 'Descartado',
    ];

    public const TIPOS = ['Comprador', 'Inversionista', 'Inquilino'];

    protected $fillable = ['nombre', 'email', 'telefono', 'tipocliente', 'estadoseguimiento', 'idusuario'];

    protected function casts(): array
    {
        return ['fecharegistro' => 'datetime'];
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function intereses(): HasMany
    {
        return $this->hasMany(Interes::class, 'idcliente', 'idcliente');
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estadoseguimiento] ?? $this->estadoseguimiento;
    }
}
