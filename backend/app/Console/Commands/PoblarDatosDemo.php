<?php

namespace App\Console\Commands;

use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Support\DatosDemo\GeneradorSqlDemo;
use App\Support\DatosDemo\InsertadorDemo;
use App\Support\DatosDemo\MarcasDemo;
use App\Support\DatosDemo\PlanDeDatosDemo;
use App\Support\DatosDemo\QuitadorDemo;
use App\Support\DatosDemo\SelectorDeSectores;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Datos FICTICIOS pero realistas para probar los reportes (CU20), repartidos en los últimos 4 meses.
 * Solo inserta filas nuevas (nunca modifica las reales) y todo lo demo se puede borrar de un solo paso con --quitar.
 *
 *   php artisan demo:poblar-reportes                      crea los datos demo (si ya existen, avisa y sale)
 *   php artisan demo:poblar-reportes --fresh              borra los demo y los vuelve a crear
 *   php artisan demo:poblar-reportes --quitar             borra TODO lo demo (y solo lo demo)
 *   php artisan demo:poblar-reportes --sql=ruta.sql       NO toca la base: escribe el mismo plan como script SQL (PostgreSQL)
 *   php artisan demo:poblar-reportes --quitar --sql=ruta  escribe el script SQL que borra lo demo
 */
class PoblarDatosDemo extends Command
{
    protected $signature = 'demo:poblar-reportes
        {--fresh : Borra los datos demo y los vuelve a crear}
        {--quitar : Borra todos los datos demo (y solo los demo)}
        {--sql= : En vez de tocar la base, escribe un script SQL en esta ruta}
        {--hoy= : Fecha de referencia AAAA-MM-DD (por defecto, hoy en Bolivia)}
        {--sectores= : Los 8 sectores de las propiedades demo, separados por coma (por defecto, los 8 con menos propiedades reales)}
        {--force : Permite correr el comando cuando APP_ENV es production}';

    protected $description = 'Crea (o borra) datos ficticios de los últimos 4 meses para probar los reportes';

    public function handle(): int
    {
        $hoy = $this->fechaDeReferencia();
        if ($hoy === null) {
            $this->error('La fecha de --hoy debe tener el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $ruta = $this->option('sql');
        if (is_string($ruta) && $ruta !== '') {
            return $this->escribirSql($ruta, $hoy);
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Este comando crea datos ficticios: no se ejecuta en producción sin --force.');

            return self::FAILURE;
        }

        if ($this->option('quitar')) {
            return $this->quitar();
        }

        if (MarcasDemo::existen()) {
            if (! $this->option('fresh')) {
                $this->warn('Ya existen datos demo: no se crea nada. Usa --fresh para recrearlos o --quitar para borrarlos.');

                return self::SUCCESS;
            }
            $this->quitar();
        }

        $sectores = $this->sectores();
        if ($sectores === null) {
            return self::FAILURE;
        }

        $insertador = app(InsertadorDemo::class);
        $conteo = $insertador->insertar((new PlanDeDatosDemo($hoy, 2026, $sectores))->construir());

        $this->info('Datos demo creados (fecha de referencia ' . $hoy->format('d/m/Y') . ', sectores: ' . implode(', ', $sectores) . '):');
        $this->mostrar($conteo, 'Creadas');
        if ($insertador->visitasOmitidas() > 0) {
            $this->line("Visitas omitidas por cruce de horario con visitas reales: {$insertador->visitasOmitidas()}");
        }
        $this->line('Para borrarlos: php artisan demo:poblar-reportes --quitar');

        return self::SUCCESS;
    }

    private function quitar(): int
    {
        if (! MarcasDemo::existen()) {
            $this->info('No hay datos demo que borrar.');

            return self::SUCCESS;
        }

        $this->info('Datos demo borrados:');
        $this->mostrar(app(QuitadorDemo::class)->quitar(), 'Borradas');

        return self::SUCCESS;
    }

    private function escribirSql(string $ruta, CarbonImmutable $hoy): int
    {
        $generador = new GeneradorSqlDemo();

        if ($this->option('quitar')) {
            $sql = $generador->quitar();
        } else {
            $sectores = $this->sectores();
            if ($sectores === null) {
                return self::FAILURE;
            }
            $sql = $generador->poblar((new PlanDeDatosDemo($hoy, 2026, $sectores))->construir(), $hoy);
            $this->line('Sectores de las propiedades demo: ' . implode(', ', $sectores));
        }

        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, $sql);

        $this->info('Script SQL escrito en ' . $ruta . ' (no se tocó la base de datos).');

        return self::SUCCESS;
    }

    /**
     * Los 8 sectores de las propiedades demo: los indicados con --sectores o, si no, los 8 con menos propiedades reales en esta base
     * (nunca Equipetrol ni Zona Norte). Devuelve null (y avisa) si lo indicado no sirve.
     *
     * @return string[]|null
     */
    private function sectores(): ?array
    {
        $texto = $this->option('sectores');

        if (! is_string($texto) || trim($texto) === '') {
            return (new SelectorDeSectores())->elegir();
        }

        $sectores = array_values(array_filter(array_map('trim', explode(',', $texto)), fn ($s) => $s !== ''));
        $error = SelectorDeSectores::errorDe($sectores);

        if ($error !== null) {
            $this->error($error);

            return null;
        }

        return $sectores;
    }

    private function fechaDeReferencia(): ?CarbonImmutable
    {
        $texto = $this->option('hoy');

        if (! is_string($texto) || $texto === '') {
            return AgendaDeVisitas::ahora()->startOfDay();
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? CarbonImmutable::parse($texto)->startOfDay() : null;
    }

    private function mostrar(array $conteo, string $columna): void
    {
        ksort($conteo);
        $this->table(['Tabla', $columna], collect($conteo)->map(fn ($n, $t) => [$t, $n])->values()->all());
    }
}
