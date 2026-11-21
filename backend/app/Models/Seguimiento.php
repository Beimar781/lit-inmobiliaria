<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contacto registrado con un cliente prospecto (CU18). */
class Seguimiento extends Model
{
    protected $table = 'seguimiento';
    protected $primaryKey = 'idseguimiento';
    public $timestamps = false;

    public const MEDIOS = [
        'LLAMADA' => 'Llamada',
        'WHATSAPP' => 'WhatsApp',
        'CORREO' => 'Correo',
        'REUNION' => 'Reunión',
        'OTRO' => 'Otro',
    ];

    protected $fillable = ['idcliente', 'idusuario', 'idpropiedad', 'medio', 'nota', 'fechacontacto', 'proximocontacto', 'fecharegistro'];

    protected function casts(): array
    {
        return [
            'fechacontacto' => 'datetime',
            'fecharegistro' => 'datetime',
            'proximocontacto' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }
}
