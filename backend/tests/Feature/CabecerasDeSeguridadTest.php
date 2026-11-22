<?php

namespace Tests\Feature;

use Tests\TestCase;

/** F-027: las respuestas web llevan cabeceras de seguridad básicas. */
class CabecerasDeSeguridadTest extends TestCase
{
    public function test_las_paginas_llevan_las_cabeceras_de_seguridad(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_tambien_las_llevan_las_redirecciones(): void
    {
        $this->get('/panel')->assertRedirect()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
