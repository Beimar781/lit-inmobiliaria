<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Visita presencial de un cliente a una propiedad (CU14 a CU17). El agente responsable es el de la propiedad. */
class Visita extends Model
{
    protected $table = 'visita';
    protected $primaryKey = 'idvisita';
    public $timestamps = false;

    public const PROGRAMADA = 'PROGRAMADA';
    public const REAGENDADA = 'REAGENDADA';
    public const CANCELADA = 'CANCELADA';
    public const REALIZADA = 'REALIZADA';

    public const ESTADOS = [
        self::PROGRAMADA => 'Programada',
        self::REAGENDADA => 'Reagendada',
        self::CANCELADA => 'Cancelada',
        self::REALIZADA => 'Realizada',
    ];

    /** Estados que ocupan el horario del agente (las canceladas y las realizadas ya no). */
    public const ACTIVAS = [self::PROGRAMADA, self::REAGENDADA];

    protected $fillable = ['idpropiedad', 'idcliente', 'idhistorial', 'fecha', 'hora', 'estado', 'observacion', 'motivo'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    /** Visitas que ocupan horario: PROGRAMADA y REAGENDADA. */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ACTIVAS);
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }

    public function historial(): BelongsTo
    {
        return $this->belongsTo(Historial::class, 'idhistorial', 'idhistorial');
    }

    /** ¿Se puede reagendar o cancelar? Solo si sigue activa. */
    public function estaActiva(): bool
    {
        return in_array($this->estado, self::ACTIVAS, true);
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Hora sin segundos: 09:00 */
    public function getHoraCortaAttribute(): string
    {
        return substr((string) $this->hora, 0, 5);
    }
}
