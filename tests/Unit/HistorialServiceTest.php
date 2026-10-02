<?php

namespace Tests\Unit;

use App\Services\HistorialService;
use PHPUnit\Framework\TestCase;

/** HistorialService::cambios decide qué se guarda en el historial de una propiedad. */
class HistorialServiceTest extends TestCase
{
    public function test_sin_diferencias_no_devuelve_nada(): void
    {
        $datos = ['precio' => '85000.00', 'estado' => 'DISPONIBLE', 'zona' => 'Equipetrol'];

        $this->assertSame([[], []], HistorialService::cambios($datos, $datos));
    }

    public function test_devuelve_solo_los_campos_que_cambiaron(): void
    {
        $antes = ['precio' => '85000.00', 'estado' => 'DISPONIBLE', 'zona' => 'Equipetrol'];
        $despues = ['precio' => '90000.00', 'estado' => 'DISPONIBLE', 'zona' => 'Sirari'];

        [$anterior, $actual] = HistorialService::cambios($antes, $despues);

        $this->assertSame(['precio' => '85000.00', 'zona' => 'Equipetrol'], $anterior);
        $this->assertSame(['precio' => '90000.00', 'zona' => 'Sirari'], $actual);
    }

    public function test_compara_como_texto_asi_que_5_y_texto_5_son_iguales(): void
    {
        [$anterior, $actual] = HistorialService::cambios(['habitaciones' => 5, 'banos' => null], ['habitaciones' => '5', 'banos' => '']);

        $this->assertSame([], $anterior);
        $this->assertSame([], $actual);
    }

    public function test_pasar_de_vacio_a_un_valor_es_un_cambio(): void
    {
        [$anterior, $actual] = HistorialService::cambios(['descripcion' => null], ['descripcion' => 'Nueva']);

        $this->assertSame(['descripcion' => null], $anterior);
        $this->assertSame(['descripcion' => 'Nueva'], $actual);
    }

    public function test_un_campo_que_no_existia_antes_se_registra_con_valor_anterior_nulo(): void
    {
        [$anterior, $actual] = HistorialService::cambios([], ['zona' => 'Centro']);

        $this->assertSame(['zona' => null], $anterior);
        $this->assertSame(['zona' => 'Centro'], $actual);
    }

    public function test_cambiar_un_valor_a_cero_se_detecta(): void
    {
        [$anterior, $actual] = HistorialService::cambios(['habitaciones' => '3'], ['habitaciones' => '0']);

        $this->assertSame(['habitaciones' => '3'], $anterior);
        $this->assertSame(['habitaciones' => '0'], $actual);
    }
}
