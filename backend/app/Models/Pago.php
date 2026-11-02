<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pago de un contrato (CU28 a CU30). monto/moneda es lo que se pagó (USD o BOB);
 * montousd es su equivalente en dólares y es el que descuenta el saldo del contrato.
 */
class Pago extends Model
{
    protected $table = 'pago';
    protected $primaryKey = 'idpago';
    public $timestamps = false;

    public const PAGADO = 'PAGADO';
    public const PENDIENTE = 'PENDIENTE';
    public const ANULADO = 'ANULADO';

    public const ESTADOS = [
        self::PAGADO => 'Pagado',
        self::PENDIENTE => 'Pendiente',
        self::ANULADO => 'Anulado',
    ];

    public const USD = 'USD';
    public const BOB = 'BOB';
    public const MONEDAS = [self::USD, self::BOB];

    public const EFECTIVO = 'EFECTIVO';
    public const TARJETA = 'TARJETA';
    public const METODOS = [
        self::EFECTIVO => 'Efectivo',
        self::TARJETA => 'Tarjeta',
    ];

    protected $fillable = [
        'idcontrato', 'monto', 'moneda', 'tipocambio', 'fechapago', 'comprobante', 'estado',
        'montousd', 'metodo', 'idusuario', 'stripe_session_id', 'stripe_payment_intent',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tipocambio' => 'decimal:4',
            'montousd' => 'decimal:2',
            'fechapago' => 'datetime',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'idcontrato', 'idcontrato');
    }

    /** Quien registró el pago. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    /** Detalle 1 a 1 con el tipo de cambio usado. */
    public function tipopago(): HasOne
    {
        return $this->hasOne(TipoPago::class, 'idpago', 'idpago');
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getMetodoEtiquetaAttribute(): string
    {
        return self::METODOS[$this->metodo] ?? (string) $this->metodo;
    }
}
