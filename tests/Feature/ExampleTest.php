<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pruebas básicas del acceso al sistema (no necesitan base de datos).
 * Ejecutar con: php artisan test
 */
class ExampleTest extends TestCase
{
    /** Quien no ha iniciado sesión es llevado al login. */
    public function test_la_raiz_redirige_al_login_si_no_hay_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_las_secciones_protegidas_piden_iniciar_sesion(): void
    {
        $this->get('/propiedades')->assertRedirect(route('login'));
        $this->get('/usuarios')->assertRedirect(route('login'));
        $this->get('/bitacora')->assertRedirect(route('login'));
    }
}
