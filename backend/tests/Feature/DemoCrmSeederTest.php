<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCrmSeeder;

/** Los agentes y prospectos de prueba del Ciclo 2 permiten ver los tres comportamientos que con un solo agente no se ven. */
class DemoCrmSeederTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoCrmSeeder::class);
    }

    private function usuarioPorCorreo(string $correo): Usuario
    {
        return Usuario::where('email', $correo)->firstOrFail();
    }

    public function test_crea_dos_agentes_nuevos_que_pueden_iniciar_sesion(): void
    {
        foreach (['csuarez@inmobiliaria.com', 'dvaca@inmobiliaria.com'] as $correo) {
            $this->post(route('login.ingresar'), ['email' => $correo, 'password' => 'Lit2026*'])->assertRedirect(route('panel'));
            $this->post(route('logout'));
        }
    }

    public function test_se_puede_ejecutar_varias_veces_sin_duplicar(): void
    {
        $usuarios = Usuario::count();
        $propiedades = Propiedad::count();
        $clientes = Cliente::count();

        $this->seed(DemoCrmSeeder::class);

        $this->assertSame($usuarios, Usuario::count());
        $this->assertSame($propiedades, Propiedad::count());
        $this->assertSame($clientes, Cliente::count());
    }

    public function test_cada_agente_tiene_su_propiedad_con_foto_y_aparece_en_el_catalogo(): void
    {
        foreach (['csuarez@inmobiliaria.com', 'dvaca@inmobiliaria.com'] as $correo) {
            $propiedad = Propiedad::where('idusuario', $this->usuarioPorCorreo($correo)->idusuario)->firstOrFail();
            $this->assertCount(1, $propiedad->imagenes);
            $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee($propiedad->titulo);
        }
    }

    public function test_comportamiento_1_el_administrador_reasigna_un_prospecto_a_otro_agente(): void
    {
        $cliente = Cliente::where('nombre', 'Hugo Salvatierra')->firstOrFail();
        $this->assertNull($cliente->idusuario);
        $diego = $this->usuarioPorCorreo('dvaca@inmobiliaria.com');

        $this->actingAs($this->usuarioPorCorreo('jgarcia@inmobiliaria.com'))
            ->patch(route('prospectos.asignar', $cliente), ['idusuario' => $diego->idusuario])
            ->assertRedirect(route('prospectos.show', $cliente));

        $this->assertSame($diego->idusuario, $cliente->fresh()->idusuario);
    }

    public function test_comportamiento_2_cada_agente_ve_solo_sus_prospectos(): void
    {
        $carla = $this->usuarioPorCorreo('csuarez@inmobiliaria.com');
        $deDiego = Cliente::where('nombre', 'Rosa Ibáñez')->firstOrFail();

        $this->actingAs($carla)->get(route('prospectos.index'))
            ->assertOk()->assertSee('Marcelo Antelo')->assertSee('Valeria Oroza')
            ->assertDontSee('Rosa Ibáñez')->assertDontSee('Jorge Peña');

        $this->get(route('prospectos.show', $deDiego))->assertForbidden();
    }

    public function test_comportamiento_3_un_agente_no_cambia_el_estado_de_la_propiedad_de_otro(): void
    {
        $diego = $this->usuarioPorCorreo('dvaca@inmobiliaria.com');
        $deCarla = Propiedad::where('idusuario', $this->usuarioPorCorreo('csuarez@inmobiliaria.com')->idusuario)->firstOrFail();

        $this->actingAs($diego)->get(route('propiedades.estado', $deCarla))->assertForbidden();
        $this->put(route('propiedades.estado.actualizar', $deCarla), ['estadopropiedad' => Propiedad::RESERVADO])->assertForbidden();

        $this->assertSame(Propiedad::DISPONIBLE, $deCarla->fresh()->estadopropiedad);
    }
}
