<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Los datos compartidos del equipo: todas las tablas del sistema en un solo archivo de texto
 * (database/datos/datos.json) más las fotos subidas (database/datos/uploads).
 *
 * Sirve para que quien clone el repositorio y ejecute `php artisan migrate:fresh --seed` vea los mismos
 * usuarios, propiedades con fotos, historial y bitácora, sin usar nada fuera de su computadora.
 *
 * Funciona con cualquier tabla: lee las tablas desde la base y las ordena según sus llaves foráneas,
 * así que los casos de uso nuevos con tablas nuevas entran solos la próxima vez que se exporte.
 */
class InstantaneaDatos
{
    /** Tablas técnicas de Laravel (sesiones, caché, colas...): no son datos del equipo. */
    private const IGNORADAS = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'users',
    ];

    private const OPCIONES_JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    private string $archivo;

    private string $fotos;

    public function __construct(?string $archivo = null, ?string $fotos = null)
    {
        $this->archivo = $archivo ?? database_path('datos/datos.json');
        $this->fotos = $fotos ?? database_path('datos/uploads');
    }

    public function archivo(): string
    {
        return $this->archivo;
    }

    public function existe(): bool
    {
        return is_file($this->archivo);
    }

    /** Tablas de datos del sistema, con las que dependen de otras después de las que las necesitan. */
    public function tablas(): array
    {
        $nombres = collect(Schema::getTables())
            ->filter(fn ($tabla) => in_array($tabla['schema'] ?? null, [null, '', 'public', 'main'], true))
            ->pluck('name')
            ->reject(fn ($nombre) => in_array($nombre, self::IGNORADAS, true) || str_starts_with($nombre, 'sqlite_'))
            ->values()
            ->all();

        $pendientes = [];
        foreach ($nombres as $nombre) {
            $pendientes[$nombre] = collect(Schema::getForeignKeys($nombre))
                ->pluck('foreign_table')
                ->unique()
                ->filter(fn ($padre) => $padre !== $nombre && in_array($padre, $nombres, true))
                ->values()
                ->all();
        }

        $orden = [];
        while ($pendientes) {
            $listas = array_keys(array_filter($pendientes, fn ($padres) => ! array_diff($padres, $orden)));

            if (! $listas) {
                $listas = [array_key_first($pendientes)]; // referencia circular: se sigue de todos modos
            }

            foreach ($listas as $tabla) {
                $orden[] = $tabla;
                unset($pendientes[$tabla]);
            }
        }

        return $orden;
    }

    /** True si alguna de las tablas del sistema ya tiene registros (no se debe cargar encima). */
    public function baseConDatos(): bool
    {
        foreach ($this->tablas() as $tabla) {
            if (DB::table($tabla)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda el contenido actual de la base y las fotos de las propiedades.
     *
     * @return array{tablas: array<string,int>, fotos: int}
     */
    public function exportar(): array
    {
        $datos = [];

        foreach ($this->tablas() as $tabla) {
            $filas = DB::table($tabla)->orderByRaw('1')->get()
                ->map(function ($fila) {
                    $fila = (array) $fila;

                    // El token de "recordarme" es de la sesión de cada persona: no se comparte.
                    if (array_key_exists('remember_token', $fila)) {
                        $fila['remember_token'] = null;
                    }

                    return $fila;
                })
                ->all();

            if ($filas) {
                $datos[$tabla] = $filas;
            }
        }

        // Un registro por línea: el archivo se puede leer y las diferencias en Git son claras.
        $bloques = [];
        foreach ($datos as $tabla => $filas) {
            $lineas = array_map(fn ($fila) => '    ' . json_encode($fila, self::OPCIONES_JSON), $filas);
            $bloques[] = '  ' . json_encode($tabla, self::OPCIONES_JSON) . ": [\n" . implode(",\n", $lineas) . "\n  ]";
        }

        File::ensureDirectoryExists(dirname($this->archivo));
        File::put($this->archivo, "{\n" . implode(",\n", $bloques) . "\n}\n");

        // Fotos: se reemplaza la carpeta completa para que no queden fotos de imágenes ya eliminadas.
        File::deleteDirectory($this->fotos);
        $copiadas = 0;

        foreach ($datos['imagen'] ?? [] as $imagen) {
            $origen = public_path($imagen['ruta']);

            if (is_file($origen)) {
                $destino = $this->fotos . '/' . Str::after($imagen['ruta'], 'uploads/');
                File::ensureDirectoryExists(dirname($destino));
                File::copy($origen, $destino);
                $copiadas++;
            }
        }

        return ['tablas' => array_map('count', $datos), 'fotos' => $copiadas];
    }

    /**
     * Carga el archivo en la base (que debe estar vacía) y copia las fotos a public/uploads.
     *
     * @return array<string,int> registros cargados por tabla
     */
    public function importar(): array
    {
        $datos = json_decode(File::get($this->archivo), true, 512, JSON_THROW_ON_ERROR);

        $desconocidas = array_diff(array_keys($datos), $this->tablas());
        if ($desconocidas) {
            throw new \RuntimeException(
                'El archivo trae tablas que tu base de datos no tiene (' . implode(', ', $desconocidas) . '). Ejecuta php artisan migrate.'
            );
        }

        $cargados = [];

        DB::transaction(function () use ($datos, &$cargados) {
            foreach ($this->tablas() as $tabla) {
                $filas = $datos[$tabla] ?? [];

                foreach (array_chunk($filas, 200) as $lote) {
                    DB::table($tabla)->insert($lote);
                }

                if ($filas) {
                    $cargados[$tabla] = count($filas);
                }
            }

            $this->continuarNumeracion(array_keys($cargados));
        });

        if (is_dir($this->fotos)) {
            File::copyDirectory($this->fotos, public_path('uploads'));
        }

        return $cargados;
    }

    /** En PostgreSQL los ids automáticos deben continuar después del último id cargado. */
    private function continuarNumeracion(array $tablas): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tablas as $tabla) {
            $primaria = collect(Schema::getIndexes($tabla))->firstWhere('primary', true);

            foreach ($primaria['columns'] ?? [] as $columna) {
                DB::select(
                    "SELECT setval(pg_get_serial_sequence(?, ?), (SELECT COALESCE(MAX(\"{$columna}\"), 1) FROM \"{$tabla}\"), "
                    . "(SELECT COUNT(*) > 0 FROM \"{$tabla}\"))",
                    [$tabla, $columna]
                );
            }
        }
    }
}
