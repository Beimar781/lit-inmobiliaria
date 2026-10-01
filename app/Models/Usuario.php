<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'idusuario';
    public $timestamps = false;

    public const ACTIVO = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';

    protected $fillable = ['nombre', 'email', 'telefono', 'password', 'estado', 'idrol'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // se guarda siempre con hash
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'idrol', 'idrol');
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ACTIVO;
    }

    /** ¿El usuario tiene alguno de estos roles? Ej: $u->tieneRol(Rol::ADMINISTRADOR) */
    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->rol?->nombre, $roles, true);
    }
}
