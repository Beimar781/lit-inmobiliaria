<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Propiedad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Visita;
use Carbon\CarbonImmutable;

/**
 * Reglas de horario de las visitas (las usan CU14, CU15 y CU17):
 *  - se atiende de 08:00 a 18:00, en bloques de 1 hora (la última visita empieza a las 17:00)
 *  - el agente responsable es el que registró la propiedad (propiedad.idusuario)
 *  - hay cruce si ese agente ya tiene una visita PROGRAMADA o REAGENDADA a la misma fecha y hora;
 *    las canceladas y las realizadas no ocupan el horario
 *  - si la propiedad no tiene agente, el cruce se mira sobre la propia propiedad
 *  - la fecha "futura" se mide con la hora de Bolivia (America/La_Paz)
 */
class AgendaDeVisitas
{
    public const ZONA = 'America/La_Paz';
    public const PRIMERA_HORA = 8;
    public const ULTIMA_HORA = 17;

    /** Las horas de inicio permitidas: 08:00, 09:00 ... 17:00. */
    public static function horas(): array
    {
        return array_map(fn (int $hora) => sprintf('%02d:00', $hora), range(self::PRIMERA_HORA, self::ULTIMA_HORA));
    }

    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA);
    }

    /** ¿La fecha y hora ya pasaron (o es justo ahora)? Ej: esFutura('2026-12-10', '09:00') */
    public static function esFutura(string $fecha, string $hora): bool
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$fecha} {$hora}", self::ZONA)->greaterThan(self::ahora());
        } catch (\Throwable) {
            return false;
        }
    }

    /** El Agente solo gestiona las visitas de las propiedades que registró; el Administrador, todas (403 si no). */
    public static function autorizar(?Usuario $usuario, Visita $visita): void
    {
        if ($usuario?->tieneRol(Rol::AGENTE)) {
            abort_unless($visita->propiedad()->where('idusuario', $usuario->idusuario)->exists(), 403, 'Esta visita no es de tus propiedades.');
        }
    }

    /** La hora tal como se guarda en la base: 09:00 -> 09:00:00. */
    public static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    /** Visitas que ocupan el horario del agente responsable de la propiedad. */
    private function activasDelResponsable(Propiedad $propiedad)
    {
        return Visita::activas()->whereHas('propiedad', fn ($q) => $propiedad->idusuario
            ? $q->where('idusuario', $propiedad->idusuario)
            : $q->where('idpropiedad', $propiedad->idpropiedad));
    }

    /** ¿El agente responsable ya tiene una visita en ese horario? $ignorar = visita que se está reagendando (CU15). */
    public function ocupado(Propiedad $propiedad, string $fecha, string $hora, ?int $ignorar = null): bool
    {
        return $this->activasDelResponsable($propiedad)
            ->where('fecha', $fecha)
            ->where('hora', self::hora($hora))
            ->when($ignorar, fn ($q) => $q->where('idvisita', '!=', $ignorar))
            ->exists();
    }

    /** Las horas de ese día en que el agente responsable está libre. */
    public function horasLibres(Propiedad $propiedad, string $fecha, ?int $ignorar = null): array
    {
        $ocupadas = $this->activasDelResponsable($propiedad)
            ->where('fecha', $fecha)
            ->when($ignorar, fn ($q) => $q->where('idvisita', '!=', $ignorar))
            ->pluck('hora')
            ->map(fn ($hora) => substr((string) $hora, 0, 5))
            ->all();

        return array_values(array_diff(self::horas(), $ocupadas));
    }

    /**
     * Horas ocupadas por día en los próximos $dias días: ['2026-12-10' => ['09:00', '11:00']].
     * Sirve para marcar en el formulario las horas que ya no se pueden elegir (el servidor igual lo vuelve a comprobar).
     */
    public function ocupadasProximas(Propiedad $propiedad, int $dias = 90, ?int $ignorar = null): array
    {
        $desde = self::ahora()->toDateString();
        $hasta = self::ahora()->addDays($dias)->toDateString();

        return $this->activasDelResponsable($propiedad)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($ignorar, fn ($q) => $q->where('idvisita', '!=', $ignorar))
            ->get(['fecha', 'hora'])
            ->groupBy(fn (Visita $visita) => $visita->fecha->format('Y-m-d'))
            ->map(fn ($visitas) => $visitas->map(fn (Visita $v) => $v->hora_corta)->unique()->sort()->values()->all())
            ->all();
    }
}
