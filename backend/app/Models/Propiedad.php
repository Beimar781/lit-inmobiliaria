<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Propiedad extends Model
{
    protected $table = 'propiedad';
    protected $primaryKey = 'idpropiedad';
    public $timestamps = false;

    public const DISPONIBLE = 'DISPONIBLE';
    public const RESERVADO = 'RESERVADO';
    public const VENDIDO = 'VENDIDO';
    public const ALQUILADO = 'ALQUILADO';
    public const BAJA = 'BAJA'; // baja lógica (CU7)

    public const ESTADOS = [self::DISPONIBLE, self::RESERVADO, self::VENDIDO, self::ALQUILADO, self::BAJA];
    public const TIPOS = ['Venta', 'Alquiler', 'Anticrético'];

    protected $fillable = [
        'idpropietario', 'idcategoria', 'idubicacion', 'idusuario',
        'titulo', 'descripcion', 'precio', 'tipopropiedad', 'estadopropiedad',
        'superficie', 'areaconstruida', 'habitaciones', 'banos', 'antiguedad',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'fecharegistro' => 'datetime',
        ];
    }

    /** Propiedades que no están dadas de baja (las del inventario interno). */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estadopropiedad', '!=', self::BAJA);
    }

    /** Propiedades que se ofrecen en el catálogo público (CU9 y CU10): solo las disponibles. */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estadopropiedad', self::DISPONIBLE);
    }

    /** Estados a los que se puede pasar según el tipo de operación (CU8). */
    public static function estadosPermitidos(?string $tipo): array
    {
        $cierre = $tipo === 'Venta' ? self::VENDIDO : self::ALQUILADO;

        return [self::DISPONIBLE, self::RESERVADO, $cierre];
    }

    /** Código visible de la propiedad: LIT-0001 (es su número de ID con ceros a la izquierda). */
    public function getCodigoAttribute(): string
    {
        return 'LIT-' . str_pad((string) $this->idpropiedad, 4, '0', STR_PAD_LEFT);
    }

    /** Enlace de WhatsApp al agente a cargo (Bolivia, +591), o null si no hay teléfono. */
    public function getWhatsappAttribute(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->agente?->telefono);

        if ($digitos === '') {
            return null;
        }

        return 'https://wa.me/' . (strlen($digitos) <= 8 ? '591' . $digitos : $digitos);
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'idpropietario', 'idpropietario');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'idcategoria', 'idcategoria');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'idubicacion', 'idubicacion');
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(Imagen::class, 'idpropiedad', 'idpropiedad');
    }

    /** Imagen de portada: la marcada como portada o, si no hay ninguna, la primera. */
    public function getImagenPrincipalAttribute(): ?Imagen
    {
        return $this->imagenes->firstWhere('portada', true) ?? $this->imagenes->first();
    }

    public function intereses(): HasMany
    {
        return $this->hasMany(Interes::class, 'idpropiedad', 'idpropiedad');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(Historial::class, 'idpropiedad', 'idpropiedad');
    }
}
