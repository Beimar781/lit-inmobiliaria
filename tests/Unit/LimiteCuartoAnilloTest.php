<?php

namespace Tests\Unit;

use App\Modules\Propiedades\Compartido\LimiteCuartoAnillo;
use Tests\TestCase;

/** Regla de alcance: solo dentro del 4.º anillo de Santa Cruz de la Sierra (config/santacruz.php). */
class LimiteCuartoAnilloTest extends TestCase
{
    public function test_la_distancia_entre_un_punto_y_si_mismo_es_cero(): void
    {
        $this->assertEqualsWithDelta(0.0, LimiteCuartoAnillo::distanciaKm(-17.78, -63.18, -17.78, -63.18), 0.0001);
    }

    public function test_un_grado_de_latitud_son_unos_111_km(): void
    {
        $this->assertEqualsWithDelta(111.19, LimiteCuartoAnillo::distanciaKm(-17.0, -63.0, -18.0, -63.0), 0.1);
    }

    public function test_la_distancia_es_simetrica(): void
    {
        $ida = LimiteCuartoAnillo::distanciaKm(-17.7630, -63.1980, -17.8040, -63.1760);
        $vuelta = LimiteCuartoAnillo::distanciaKm(-17.8040, -63.1760, -17.7630, -63.1980);

        $this->assertEqualsWithDelta($ida, $vuelta, 0.00001);
    }

    public function test_el_centro_de_la_ciudad_esta_dentro(): void
    {
        $centro = config('santacruz.centro');

        $this->assertTrue(LimiteCuartoAnillo::contiene($centro['lat'], $centro['lng']));
    }

    public function test_barrios_del_cuarto_anillo_estan_dentro(): void
    {
        $this->assertTrue(LimiteCuartoAnillo::contiene(-17.7630, -63.1980)); // Equipetrol
        $this->assertTrue(LimiteCuartoAnillo::contiene(-17.8040, -63.1760)); // Las Palmas
        $this->assertTrue(LimiteCuartoAnillo::contiene(-17.7545, -63.1650)); // Urbarí
    }

    public function test_puntos_lejanos_estan_fuera(): void
    {
        $this->assertFalse(LimiteCuartoAnillo::contiene(-17.5000, -63.1700)); // Warnes
        $this->assertFalse(LimiteCuartoAnillo::contiene(-17.9500, -63.2500)); // sur lejano
        $this->assertFalse(LimiteCuartoAnillo::contiene(-16.5000, -68.1500)); // La Paz
        $this->assertFalse(LimiteCuartoAnillo::contiene(40.4168, -3.7038));   // Madrid
    }

    public function test_el_borde_del_radio_configurado(): void
    {
        $centro = config('santacruz.centro');
        $radio = config('santacruz.radio_km');
        $kmPorGrado = 111.19;

        $justoDentro = $centro['lat'] + (($radio - 0.05) / $kmPorGrado);
        $justoFuera = $centro['lat'] + (($radio + 0.05) / $kmPorGrado);

        $this->assertTrue(LimiteCuartoAnillo::contiene($justoDentro, $centro['lng']));
        $this->assertFalse(LimiteCuartoAnillo::contiene($justoFuera, $centro['lng']));
    }

    public function test_si_cambia_el_radio_en_la_configuracion_cambia_el_resultado(): void
    {
        $this->assertFalse(LimiteCuartoAnillo::contiene(-17.5000, -63.1700));

        config(['santacruz.radio_km' => 50]);

        $this->assertTrue(LimiteCuartoAnillo::contiene(-17.5000, -63.1700));
    }
}
