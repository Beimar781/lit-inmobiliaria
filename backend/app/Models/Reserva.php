<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $fillable = ['idcliente', 'idpropiedad', 'idvisita', 'fechareserva', 'fechaplazo', 'estado', 'montousd', 'idusuario'];

    protected function casts(): array
    {
        return [
            'fechareserva' => 'date:Y-m-d',
            'fechaplazo' => 'date:Y-m-d',
            'montousd' => 'decimal:2',
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

    /** La visita realizada en la que se apoya la reserva. */
    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class, 'idvisita', 'idvisita');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoReserva::class, 'idreserva', 'idreserva');
    }

    public function cobro(): HasOne
    {
        return $this->hasOne(MovimientoReserva::class, 'idreserva', 'idreserva')->where('tipo', MovimientoReserva::COBRO);
    }

    public function devolucion(): HasOne
    {
        return $this->hasOne(MovimientoReserva::class, 'idreserva', 'idreserva')->where('tipo', MovimientoReserva::DEVOLUCION);
    }

    /**
     * ¿Hay que devolverle dinero al cliente? Sí si pagó, la reserva venció o se canceló (no formalizó la compra)
     * y todavía no se le devolvió.
     */
    public function devolucionPendiente(): bool
    {
        return in_array($this->estado, [self::VENCIDA, self::CANCELADA], true)
            && $this->montousd !== null
            && ! $this->movimientos()->where('tipo', MovimientoReserva::DEVOLUCION)->exists();
    }

    /** Alcance: reservas "activas" (las que se ven por defecto): no se ocultan hasta que se devuelva lo que corresponda. */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('estado', [self::VIGENTE, self::CONCRETADA])
                ->orWhere(function ($pendiente) {
                    $pendiente->whereIn('estado', [self::VENCIDA, self::CANCELADA])
                        ->whereNotNull('montousd')
                        ->whereDoesntHave('movimientos', fn ($m) => $m->where('tipo', MovimientoReserva::DEVOLUCION));
                });
        });
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
