<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Contrato de venta, alquiler o anticrético de una propiedad (CU27). El monto está en dólares. */
class Contrato extends Model
{
    protected $table = 'contrato';
    protected $primaryKey = 'idcontrato';
    public $timestamps = false;

    public const VIGENTE = 'VIGENTE';
    public const FINALIZADO = 'FINALIZADO';
    public const RESCINDIDO = 'RESCINDIDO';

    public const ESTADOS = [
        self::VIGENTE => 'Vigente',
        self::FINALIZADO => 'Finalizado',
        self::RESCINDIDO => 'Rescindido',
    ];

    public const TIPOS = ['Venta', 'Alquiler', 'Anticrético'];

    protected $fillable = [
        'idpropiedad', 'idcliente', 'idusuario', 'idreserva',
        'tipocontrato', 'monto', 'fechainicio', 'fechafin', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fechainicio' => 'date:Y-m-d',
            'fechafin' => 'date:Y-m-d',
        ];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::VIGENTE);
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'idpropiedad', 'idpropiedad');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idcliente', 'idcliente');
    }

    /** Agente que gestionó el contrato. */
    public function agente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'idreserva', 'idreserva');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'idcontrato', 'idcontrato');
    }

    /**
     * Dólares ya pagados: la suma del equivalente en USD de los pagos PAGADOS (los pendientes y anulados no cuentan).
     * Si el contrato viene de una reserva, el dinero de la reserva ya está aquí como su primer pago (pago inicial o total).
     */
    public function totalPagadoUsd(): float
    {
        $centavos = (int) round(((float) $this->pagos()->where('estado', Pago::PAGADO)->sum('montousd')) * 100);

        return $centavos / 100;
    }

    /** Dólares que faltan por pagar: monto del contrato menos lo pagado. */
    public function saldoUsd(): float
    {
        return (round(((float) $this->monto) * 100) - round($this->totalPagadoUsd() * 100)) / 100;
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }
}
