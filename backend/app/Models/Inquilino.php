<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Datos de arrendamiento de un cliente (CU24). Un inquilino por cliente. */
class Inquilino extends Model
{
    protected $table = 'inquilino';
    protected $primaryKey = 'idinquilino';
    public $timestamps = false;

    public const GARANTIAS = [
        'DEPOSITO' => 'Depósito',
        'GARANTE' => 'Garante',
        'OTRA' => 'Otra',
        'NINGUNA' => 'Ninguna',
    ];

    public const ESTADOS = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];

    protected $fillable = ['idcliente', 'ocupacion', 'tipogarantia', 'detallegarantia', 'estado', 'fecharegistro'];

    protected function casts(): array
    {
        return ['fecharegistro' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }
}
