<?php

namespace Tests\Feature;

use Tests\TestCase;

class ZonaHorariaTest extends TestCase
{
    public function test_la_aplicacion_usa_la_hora_de_bolivia_por_defecto(): void
    {
        $this->assertSame('America/La_Paz', config('app.timezone'));
        $this->assertSame('America/La_Paz', now()->getTimezone()->getName());
    }

    public function test_la_conexion_a_postgresql_fija_la_misma_zona_horaria(): void
    {
        $this->assertSame('America/La_Paz', config('database.connections.pgsql.timezone'));
    }
}
