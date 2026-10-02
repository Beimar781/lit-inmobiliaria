<?php

namespace Tests\Feature;

use App\Models\Imagen;
use App\Models\Propiedad;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

/** Reglas de negocio que viven en los modelos. */
class ModelosTest extends SistemaTestCase
{
    public function test_existen_los_cuatro_roles(): void
    {
        $this->assertEqualsCanonicalizing(
            [Rol::ADMINISTRADOR, Rol::AGENTE, Rol::ASISTENTE, Rol::CLIENTE],
            Rol::pluck('nombre')->all()
        );
    }

    public function test_tiene_rol_reconoce_el_rol_del_usuario(): void
    {
        $agente = $this->agente();

        $this->assertTrue($agente->tieneRol(Rol::AGENTE));
        $this->assertTrue($agente->tieneRol(Rol::ADMINISTRADOR, Rol::AGENTE));
        $this->assertFalse($agente->tieneRol(Rol::ADMINISTRADOR));
        $this->assertFalse($agente->tieneRol());
    }

    public function test_esta_activo_depende_del_estado(): void
    {
        $this->assertTrue($this->agente()->estaActivo());
        $this->assertFalse($this->agente(['estado' => Usuario::INACTIVO])->estaActivo());
    }

    public function test_la_contrasena_siempre_se_guarda_con_hash_y_no_se_expone(): void
    {
        $usuario = $this->agente();

        $this->assertNotSame(self::CLAVE, $usuario->password);
        $this->assertTrue(Hash::check(self::CLAVE, $usuario->password));
        $this->assertArrayNotHasKey('password', $usuario->toArray());
    }

    public function test_el_correo_del_usuario_es_unico_en_la_base_de_datos(): void
    {
        $this->agente(['email' => 'unico@test.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->agente(['email' => 'unico@test.com']);
    }

    public function test_un_rol_con_usuarios_no_se_puede_borrar(): void
    {
        $this->agente();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Rol::where('nombre', Rol::AGENTE)->delete();
    }

    public function test_el_alcance_vigentes_excluye_las_propiedades_dadas_de_baja(): void
    {
        $this->propiedad(['titulo' => 'A']);
        $this->propiedad(['titulo' => 'B', 'estadopropiedad' => Propiedad::VENDIDO]);
        $this->propiedad(['titulo' => 'C', 'estadopropiedad' => Propiedad::BAJA]);

        $this->assertEqualsCanonicalizing(['A', 'B'], Propiedad::vigentes()->pluck('titulo')->all());
    }

    public function test_la_imagen_principal_es_la_portada_o_si_no_hay_la_primera(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 3);
        $segunda = $propiedad->imagenes[1];

        Imagen::where('idpropiedad', $propiedad->idpropiedad)->update(['portada' => false]);
        $segunda->update(['portada' => true]);
        $this->assertSame($segunda->idimagen, $propiedad->fresh('imagenes')->imagenPrincipal->idimagen);

        Imagen::where('idpropiedad', $propiedad->idpropiedad)->update(['portada' => false]);
        $this->assertSame($propiedad->imagenes[0]->idimagen, $propiedad->fresh('imagenes')->imagenPrincipal->idimagen);

        $this->assertNull($this->propiedad()->imagenPrincipal);
    }

    public function test_la_propiedad_nueva_queda_disponible_por_defecto_con_fecha_de_registro(): void
    {
        $propiedad = Propiedad::create(['titulo' => 'Mínima', 'precio' => 100])->fresh();

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->estadopropiedad);
        $this->assertNotNull($propiedad->fecharegistro);
    }

    public function test_el_precio_se_maneja_con_dos_decimales(): void
    {
        $this->assertSame('1234.50', (string) $this->propiedad(['precio' => 1234.5])->fresh()->precio);
    }

    public function test_al_borrar_una_propiedad_se_borran_sus_imagenes_en_cascada(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 2);
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');

        $propiedad->delete();

        $this->assertSame(0, Imagen::count());
    }

    public function test_las_relaciones_de_la_propiedad(): void
    {
        $agente = $this->agente();
        $propiedad = $this->conImagenes($this->propiedad([], $agente), 1)->fresh(['categoria', 'propietario', 'ubicacion', 'agente', 'imagenes']);

        $this->assertSame('Casa', $propiedad->categoria->nombre);
        $this->assertSame('Luis Parada', $propiedad->propietario->nombre);
        $this->assertSame('Equipetrol', $propiedad->ubicacion->zona);
        $this->assertSame($agente->idusuario, $propiedad->agente->idusuario);
        $this->assertCount(1, $propiedad->imagenes);
    }
}
