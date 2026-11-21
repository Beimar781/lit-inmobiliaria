<?php

namespace App\Console\Commands;

use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Support\DatosDemo\GeneradorSqlDemo;
use App\Support\DatosDemo\InsertadorDemo;
use App\Support\DatosDemo\MarcasDemo;
use App\Support\DatosDemo\PlanDeDatosDemo;
use App\Support\DatosDemo\QuitadorDemo;
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

        $insertador = app(InsertadorDemo::class);
        $conteo = $insertador->insertar((new PlanDeDatosDemo($hoy))->construir());

        $this->info('Datos demo creados (fecha de referencia ' . $hoy->format('d/m/Y') . '):');
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
        $sql = $this->option('quitar')
            ? $generador->quitar()
            : $generador->poblar((new PlanDeDatosDemo($hoy))->construir(), $hoy);

        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, $sql);

        $this->info('Script SQL escrito en ' . $ruta . ' (no se tocó la base de datos).');

        return self::SUCCESS;
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
