<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Detalle 1 a 1 de un pago: el tipo de cambio oficial usado ese día (CU28). Su llave es la del pago. */
class TipoPago extends Model
{
    protected $table = 'tipopago';
    protected $primaryKey = 'idpago';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['idpago', 'cambiodemonedalocal', 'fecha'];

    protected function casts(): array
    {
        return [
            'cambiodemonedalocal' => 'decimal:4',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'idpago', 'idpago');
    }
}
