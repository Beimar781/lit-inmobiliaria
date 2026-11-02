<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pago de un contrato (CU28 a CU30). monto/moneda es lo que se pagó (USD o BOB);
 * montousd es su equivalente en dólares y es el que descuenta el saldo del contrato.
 * Un pago con tarjeta siempre es en USD; si el cliente pagó en bolivianos en la página de Stripe (Adaptive Pricing), lo
 * que pagó está en moneda_cliente / monto_cliente / tipocambio_stripe (el tipocambio manual solo existe en el efectivo).
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
        'moneda_cliente', 'monto_cliente', 'tipocambio_stripe',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tipocambio' => 'decimal:4',
            'montousd' => 'decimal:2',
            'monto_cliente' => 'decimal:2',
            'tipocambio_stripe' => 'decimal:6',
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

    /**
     * ¿El cliente pagó con tarjeta en otra moneda (por ejemplo bolivianos) en la página de Stripe? El pago igual se registra
     * en dólares; estos datos (moneda, monto y tipo de cambio de Stripe) son lo que el cliente realmente pagó.
     */
    public function pagoEnMonedaDelCliente(): bool
    {
        return $this->monto_cliente !== null && $this->moneda_cliente !== null;
    }

    /** Tipo de cambio de Stripe sin ceros de más (por ejemplo «6.96»). */
    public function tipoCambioStripeTexto(): string
    {
        return rtrim(rtrim(number_format((float) $this->tipocambio_stripe, 6, '.', ''), '0'), '.');
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
