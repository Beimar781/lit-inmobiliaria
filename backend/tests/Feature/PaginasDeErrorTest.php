<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** F-013: las páginas de error salen en español, no con la pantalla en inglés de Laravel. */
class PaginasDeErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_el_error_500_se_muestra_en_espanol(): void
    {
        Route::get('/_prueba/error', fn () => throw new \RuntimeException('falla interna secreta'));

        $this->get('/_prueba/error')
            ->assertStatus(500)
            ->assertSee('Algo salió mal')
            ->assertDontSee('falla interna secreta');
    }

    public function test_el_metodo_no_permitido_se_muestra_en_espanol(): void
    {
        $this->post('/login-que-no-existe-como-post-xyz')->assertStatus(404);
        $this->get('/logout')->assertStatus(405)->assertSee('Acción no permitida');
    }

    public function test_las_paginas_401_y_503_existen_en_espanol(): void
    {
        $this->assertStringContainsString('Acceso no autorizado', view('errors.401')->render());
        $this->assertStringContainsString('Estamos en mantenimiento', view('errors.503')->render());
    }
}
