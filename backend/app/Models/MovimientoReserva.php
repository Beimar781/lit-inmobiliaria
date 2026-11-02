<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dinero en efectivo de una reserva (CU26): el cobro al reservar y la devolución si el cliente no formaliza la compra. */
class MovimientoReserva extends Model
{
    protected $table = 'movimientoreserva';
    protected $primaryKey = 'idmovimiento';
    public $timestamps = false;

    public const COBRO = 'COBRO';
    public const DEVOLUCION = 'DEVOLUCION';

    /** Prefijo del número de comprobante: RV-2026-000001 (reserva) y DV-2026-000001 (devolución). */
    public const PREFIJOS = [self::COBRO => 'RV', self::DEVOLUCION => 'DV'];

    protected $fillable = [
        'idreserva', 'tipo', 'monto', 'moneda', 'tipocambio', 'montousd',
        'porcentajeretencion', 'comprobante', 'fecha', 'idusuario',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tipocambio' => 'decimal:4',
            'montousd' => 'decimal:2',
            'porcentajeretencion' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'idreserva', 'idreserva');
    }

    /** Quien manejó el efectivo. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function getTipoEtiquetaAttribute(): string
    {
        return $this->tipo === self::COBRO ? 'Cobro de la reserva' : 'Devolución de la reserva';
    }
}
