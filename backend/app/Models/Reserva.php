<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Reserva de una propiedad para un cliente hasta una fecha plazo (CU26). */
class Reserva extends Model
{
    protected $table = 'reserva';
    protected $primaryKey = 'idreserva';
    public $timestamps = false;

    public const VIGENTE = 'VIGENTE';
    public const CANCELADA = 'CANCELADA';
    public const CONCRETADA = 'CONCRETADA'; // pasó a contrato (CU27)
    public const VENCIDA = 'VENCIDA';       // se acabó el plazo

    public const ESTADOS = [
        self::VIGENTE => 'Vigente',
        self::CANCELADA => 'Cancelada',
        self::CONCRETADA => 'Concretada',
        self::VENCIDA => 'Vencida',
    ];

    protected $fillable = ['idcliente', 'idpropiedad', 'fechareserva', 'fechaplazo', 'estado', 'idusuario'];

    protected function casts(): array
    {
        return [
            'fechareserva' => 'date:Y-m-d',
            'fechaplazo' => 'date:Y-m-d',
        ];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::VIGENTE);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }

    /** Agente que registró la reserva. */
    public function agente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    /** Contrato en que se concretó la reserva, si lo hubo. */
    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class, 'idreserva', 'idreserva');
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
