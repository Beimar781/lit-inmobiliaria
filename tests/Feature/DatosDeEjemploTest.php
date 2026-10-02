<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Rol;
use App\Models\Usuario;
use App\Modules\Propiedades\Compartido\LimiteCuartoAnillo;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PropiedadSeeder;
use Illuminate\Support\Facades\Hash;

/** Los datos de ejemplo del README (usuarios de prueba y 8 propiedades). */
class DatosDeEjemploTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_se_crean_los_cuatro_usuarios_de_prueba_con_la_clave_del_readme(): void
    {
        $esperados = [
            'jgarcia@inmobiliaria.com' => Rol::ADMINISTRADOR,
            'mtorrez@inmobiliaria.com' => Rol::AGENTE,
            'rjustiniano@inmobiliaria.com' => Rol::ASISTENTE,
            'croca@gmail.com' => Rol::CLIENTE,
        ];

        foreach ($esperados as $correo => $rol) {
            $usuario = Usuario::where('email', $correo)->first();
            $this->assertNotNull($usuario, $correo);
            $this->assertSame($rol, $usuario->rol->nombre);
            $this->assertTrue($usuario->estaActivo());
            $this->assertTrue(Hash::check('Lit2026*', $usuario->password));
        }
    }

    public function test_los_usuarios_de_prueba_pueden_iniciar_sesion(): void
    {
        foreach (['jgarcia@inmobiliaria.com', 'mtorrez@inmobiliaria.com'] as $correo) {
            $this->post(route('login.ingresar'), ['email' => $correo, 'password' => 'Lit2026*'])
                ->assertRedirect(route('panel'));
            $this->post(route('logout'));
        }
    }

    public function test_hay_5_categorias_y_2_propietarios(): void
    {
        $this->assertSame(5, Categoria::count());
        $this->assertSame(2, Propietario::count());
    }

    public function test_hay_8_propiedades_de_ejemplo_dentro_del_cuarto_anillo(): void
    {
        $propiedades = Propiedad::with('ubicacion')->get();

        $this->assertCount(8, $propiedades);
        foreach ($propiedades as $propiedad) {
            $this->assertTrue(
                LimiteCuartoAnillo::contiene((float) $propiedad->ubicacion->latitud, (float) $propiedad->ubicacion->longitud),
                "«{$propiedad->titulo}» fuera del 4.º anillo"
            );
        }
    }

    public function test_cada_propiedad_de_ejemplo_tiene_portada_imagen_en_disco_e_historial(): void
    {
        foreach (Propiedad::with('imagenes')->get() as $propiedad) {
            $this->assertNotEmpty($propiedad->imagenes, $propiedad->titulo);
            $this->assertSame(1, $propiedad->imagenes->where('portada', true)->count(), $propiedad->titulo);
            foreach ($propiedad->imagenes as $imagen) {
                $this->assertFileExists($this->rutaFisica($imagen->ruta));
            }
            $this->assertSame(1, Historial::where('idpropiedad', $propiedad->idpropiedad)->where('tipo', Historial::REGISTRO)->count());
        }
    }

    public function test_las_propiedades_de_ejemplo_cubren_los_tipos_y_estados(): void
    {
        $this->assertEqualsCanonicalizing(['Venta', 'Alquiler', 'Anticrético'], Propiedad::distinct()->pluck('tipopropiedad')->all());
        $this->assertEqualsCanonicalizing(
            ['DISPONIBLE', 'RESERVADO', 'VENDIDO', 'ALQUILADO'],
            Propiedad::distinct()->pluck('estadopropiedad')->all()
        );
    }

    public function test_ejecutar_el_seeder_otra_vez_no_duplica_nada(): void
    {
        $this->seed(PropiedadSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, Propiedad::count());
        $this->assertSame(4, Usuario::count());
        $this->assertSame(8, Historial::count());
    }

    public function test_el_administrador_ve_las_8_propiedades_en_el_inventario(): void
    {
        $admin = Usuario::where('email', 'jgarcia@inmobiliaria.com')->first();

        $this->actingAs($admin)->get(route('propiedades.index'))
            ->assertViewHas('propiedades', fn ($p) => $p->total() === 8 && $p->count() === 8);
    }
}
