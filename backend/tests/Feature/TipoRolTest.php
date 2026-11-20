<?php

namespace Tests\Feature;

use App\Models\Rol;

/** Atributo "tipo" del rol (opcional, hasta 50 caracteres). No hay pantallas para gestionar roles: se prueba el modelo. */
class TipoRolTest extends SistemaTestCase
{
    public function test_el_tipo_es_opcional_y_se_guarda(): void
    {
        $sinTipo = Rol::create(['nombre' => 'Rol sin tipo']);
        $this->assertNull($sinTipo->fresh()->tipo);

        $conTipo = Rol::create(['nombre' => 'Rol con tipo', 'tipo' => 'Interno']);
        $this->assertSame('Interno', $conTipo->fresh()->tipo);
    }

    public function test_el_tipo_se_modifica_y_se_borra(): void
    {
        $rol = Rol::create(['nombre' => 'Rol demo', 'tipo' => 'Interno']);

        $rol->update(['tipo' => 'Externo']);
        $this->assertSame('Externo', $rol->fresh()->tipo);

        $rol->update(['tipo' => null]);
        $this->assertNull($rol->fresh()->tipo);
    }

    public function test_la_columna_admite_50_caracteres(): void
    {
        $rol = Rol::create(['nombre' => 'Rol largo', 'tipo' => str_repeat('a', 50)]);

        $this->assertSame(50, strlen($rol->fresh()->tipo));
    }
}
