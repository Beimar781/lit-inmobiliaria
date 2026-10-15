<?php

namespace App\Console\Commands;

use App\Services\InstantaneaDatos;
use Illuminate\Console\Command;

/**
 * Guarda los datos actuales (usuarios, propiedades, fotos, historial, bitácora...) en database/datos
 * para compartirlos con el equipo por Git. Ver README, sección 7.
 */
class ExportarDatos extends Command
{
    protected $signature = 'datos:exportar';

    protected $description = 'Guarda los datos actuales y las fotos en database/datos para compartirlos con el equipo';

    public function handle(): int
    {
        $resultado = (new InstantaneaDatos())->exportar();

        $this->table(
            ['Tabla', 'Registros'],
            collect($resultado['tablas'])->map(fn ($total, $tabla) => [$tabla, $total])->values()->all()
        );
        $this->info("Fotos copiadas: {$resultado['fotos']}");
        $this->line('Archivos actualizados: backend/database/datos/datos.json y backend/database/datos/uploads');
        $this->line('Revisa los cambios con git status y avisa al grupo antes de subirlos.');

        return self::SUCCESS;
    }
}
