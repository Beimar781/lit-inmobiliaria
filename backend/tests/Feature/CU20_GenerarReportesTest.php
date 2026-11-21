<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Usuario;
use App\Models\Visita;
use OpenSpout\Reader\XLSX\Reader;

/** CU20: Generar reportes con filtros manuales y exportación (pasos 2 a 7 del documento, 6.2.4; el paso 1 se prepara aquí). */
class CU20_GenerarReportesTest extends SistemaTestCase
{
    private function cliente20(string $nombre = 'Cliente Reporte'): Cliente
    {
        static $n = 0;
        $n++;

        return Cliente::create(['nombre' => $nombre, 'email' => "rep20_{$n}@test.com", 'telefono' => '70011122']);
    }

    private function contrato(float $monto, string $inicio, string $estado = Contrato::VIGENTE, ?Cliente $cliente = null, ?Propiedad $propiedad = null): Contrato
    {
        return Contrato::create([
            'idpropiedad' => ($propiedad ?? $this->propiedad())->idpropiedad, 'idcliente' => ($cliente ?? $this->cliente20())->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => $inicio, 'estado' => $estado,
        ]);
    }

    private function pago(Contrato $contrato, float $monto, string $estado = Pago::PAGADO, string $fecha = '2026-12-02 10:00:00'): Pago
    {
        return Pago::create([
            'idcontrato' => $contrato->idcontrato, 'monto' => $monto, 'montousd' => $monto, 'moneda' => Pago::USD,
            'fechapago' => $fecha, 'estado' => $estado, 'metodo' => Pago::EFECTIVO,
        ]);
    }

    private function visita(Propiedad $propiedad, string $fecha = '2026-12-10', string $estado = Visita::PROGRAMADA): Visita
    {
        return Visita::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $this->cliente20()->idcliente, 'fecha' => $fecha, 'hora' => '09:00', 'estado' => $estado]);
    }

    private function reporte(array $parametros)
    {
        return $this->get(route('reportes.generar', $parametros));
    }

    public function test_paso_2_generar_un_reporte_eligiendo_tipo_y_rango_de_fechas_muestra_las_filas_del_rango_y_los_totales(): void
    {
        $this->contrato(1000, '2026-10-31');
        $this->contrato(2500.50, '2026-11-05');
        $this->contrato(499.50, '2026-11-20');
        $this->contrato(9999, '2026-12-01');
        $this->actingAs($this->admin());

        $this->get(route('reportes.index'))->assertOk()->assertSee('<span class="cu-etiqueta">(CU20)</span>', false);
        $this->reporte(['tipo' => 'contratos', 'desde' => '2026-11-01', 'hasta' => '2026-11-30'])->assertOk()
            ->assertSee('Reporte de contratos')->assertSee('2,500.50')->assertSee('499.50')
            ->assertDontSee('9,999.00')->assertDontSee('1,000.00')
            ->assertSee('Total de filas')->assertSee('Suma de montos (USD)')->assertSee('3,000.00')
            ->assertSee('Exportar PDF')->assertSee('Exportar Excel');
        $this->assertSame(1, Bitacora::where('accion', 'REPORTE_GENERADO')->where('modulo', 'Reportes')->count());
        $this->assertSame('contratos', Bitacora::where('accion', 'REPORTE_GENERADO')->first()->detalle['tipo']);
    }

    public function test_paso_3_un_rango_invertido_muestra_el_mensaje_de_rango_no_valido(): void
    {
        $this->contrato(1000, '2026-11-05');
        $this->actingAs($this->admin());

        $this->reporte(['tipo' => 'contratos', 'desde' => '2026-11-30', 'hasta' => '2026-11-01'])->assertOk()
            ->assertSee('El rango de fechas no es válido.')->assertDontSee('Total de filas')->assertDontSee('Exportar PDF');
        $this->reporte(['tipo' => 'contratos', 'desde' => 'zzz'])->assertOk()->assertSee('El rango de fechas no es válido.');
        $this->reporte(['tipo' => 'contratos', 'desde' => '2026-02-31'])->assertOk()->assertSee('El rango de fechas no es válido.');
        $this->reporte(['tipo' => 'contratos', 'hasta' => ['x']])->assertOk()->assertSee('El rango de fechas no es válido.');
    }

    public function test_paso_4_el_agente_que_pide_un_reporte_de_pagos_es_denegado_con_el_mensaje(): void
    {
        $this->actingAs($this->agente());

        $this->reporte(['tipo' => 'pagos'])->assertForbidden()->assertSee('Este reporte no está permitido para tu rol.');
        $this->reporte(['tipo' => 'contratos'])->assertForbidden()->assertSee('Este reporte no está permitido para tu rol.');
        $this->get(route('reportes.exportar', ['formato' => 'pdf', 'tipo' => 'pagos']))->assertForbidden()
            ->assertSee('Este reporte no está permitido para tu rol.');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
    }

    public function test_paso_5_el_agente_que_pide_un_reporte_de_visitas_solo_ve_las_de_sus_propiedades(): void
    {
        $agente = $this->agente();
        $otro = $this->agente();
        $mia = $this->propiedad(['titulo' => 'Mi Casa Propia'], $agente);
        $ajena = $this->propiedad(['titulo' => 'Casa Del Otro'], $otro);
        $this->visita($mia);
        $this->visita($ajena);
        $this->actingAs($agente);

        $this->reporte(['tipo' => 'visitas'])->assertOk()->assertSee('Mi Casa Propia')->assertDontSee('Casa Del Otro');
        // Intentar mirar a otro agente con el filtro no le abre lo ajeno
        $this->reporte(['tipo' => 'visitas', 'idagente' => (string) $otro->idusuario])->assertOk()->assertSee('Mi Casa Propia')->assertDontSee('Casa Del Otro');
    }

    public function test_paso_6_exportar_a_pdf_responde_200_con_application_pdf_y_contenido(): void
    {
        $this->contrato(1234.56, '2026-11-05');
        $admin = $this->admin(['nombre' => 'Ada Admin']);
        $this->actingAs($admin);

        $respuesta = $this->get(route('reportes.exportar', ['formato' => 'pdf', 'tipo' => 'contratos', 'desde' => '2026-11-01']))->assertOk();

        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertGreaterThan(1500, strlen($respuesta->getContent()));
        $this->assertSame(1, Bitacora::where('accion', 'REPORTE_EXPORTADO')->where('modulo', 'Reportes')->count());
        $this->assertSame('pdf', Bitacora::where('accion', 'REPORTE_EXPORTADO')->first()->detalle['formato']);
    }

    public function test_paso_7_exportar_a_excel_responde_200_y_el_archivo_se_abre_con_filas_y_totales(): void
    {
        $cliente = $this->cliente20('=1+1');
        $this->contrato(1000, '2026-11-05', Contrato::VIGENTE, $cliente);
        $this->contrato(250.25, '2026-11-06');
        $admin = $this->admin(['nombre' => 'Ada Admin']);
        $this->actingAs($admin);

        $respuesta = $this->get(route('reportes.exportar', ['formato' => 'xlsx', 'tipo' => 'contratos']))->assertOk();

        $this->assertStringContainsString('spreadsheetml.sheet', $respuesta->headers->get('Content-Type'));
        $ruta = $respuesta->baseResponse->getFile()->getPathname();
        $filas = [];
        $lector = new Reader();
        $lector->open($ruta);
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
        }
        $lector->close();
        @unlink($ruta);

        $texto = json_encode($filas, JSON_UNESCAPED_UNICODE);
        $this->assertSame('Reporte de contratos', $filas[0][0]);
        $this->assertStringContainsString('Generado por: Ada Admin', $texto);
        $encabezados = array_values(array_filter($filas, fn ($f) => ($f[0] ?? null) === 'Propiedad'));
        $this->assertCount(1, $encabezados);
        $this->assertSame(['Propiedad', 'Cliente', 'Tipo', 'Monto (USD)', 'Estado', 'Fecha de inicio'], $encabezados[0]);
        $datos = array_values(array_filter($filas, fn ($f) => ($f[2] ?? null) === 'Venta'));
        $this->assertCount(2, $datos);
        $this->assertEqualsWithDelta(250.25, $datos[0][3], 0.001);
        $this->assertSame('=1+1', $datos[1][1], 'Un texto que empieza con "=" queda como texto, no como fórmula.');
        $ultimas = array_slice($filas, -2);
        $this->assertSame('Total de filas', $ultimas[0][0]);
        $this->assertEquals(2, $ultimas[0][1]);
        $this->assertSame('Suma de montos (USD)', $ultimas[1][0]);
        $this->assertEqualsWithDelta(1250.25, $ultimas[1][1], 0.001);
    }

    public function test_el_asistente_ve_contratos_y_pagos_y_no_propiedades_clientes_ni_visitas(): void
    {
        $contrato = $this->contrato(800, '2026-11-05');
        $this->pago($contrato, 300);
        $this->actingAs($this->asistente());

        $this->reporte(['tipo' => 'contratos'])->assertOk()->assertSee('800.00');
        $this->reporte(['tipo' => 'pagos'])->assertOk()->assertSee('300.00');
        foreach (['propiedades', 'clientes', 'visitas'] as $tipo) {
            $this->reporte(['tipo' => $tipo])->assertForbidden()->assertSee('Este reporte no está permitido para tu rol.');
        }
        $this->get(route('reportes.index'))->assertOk()->assertDontSee('value="propiedades"', false)->assertSee('value="pagos"', false);
    }

    public function test_propiedades_disponibles_por_categoria_da_las_cantidades_correctas(): void
    {
        $casa = $this->categoria('Casa');
        $depto = $this->categoria('Departamento');
        foreach ([[$casa, 'DISPONIBLE', 100000], [$casa, 'DISPONIBLE', 50000], [$depto, 'DISPONIBLE', 70000], [$casa, 'VENDIDO', 1], [$depto, 'BAJA', 2]] as [$categoria, $estado, $precio]) {
            $this->propiedad(['idcategoria' => $categoria->idcategoria, 'estadopropiedad' => $estado, 'precio' => $precio]);
        }
        $this->actingAs($this->admin());

        $respuesta = $this->reporte(['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria'])->assertOk();

        $respuesta->assertSee('Por categoría')->assertSee('Suma de precios (USD)')->assertSee('220,000.00');
        $this->assertMatchesRegularExpression('/<td[^>]*>Casa<\/td>\s*<td[^>]*>2<\/td>\s*<td[^>]*>150,000\.00<\/td>/', $respuesta->getContent());
        $this->assertMatchesRegularExpression('/<td[^>]*>Departamento<\/td>\s*<td[^>]*>1<\/td>\s*<td[^>]*>70,000\.00<\/td>/', $respuesta->getContent());
    }

    public function test_las_propiedades_dadas_de_baja_solo_salen_si_el_filtro_estado_las_pide(): void
    {
        $this->propiedad(['titulo' => 'Vigente Uno']);
        $this->propiedad(['titulo' => 'Dada De Baja', 'estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->admin());

        $this->reporte(['tipo' => 'propiedades'])->assertOk()->assertSee('Vigente Uno')->assertDontSee('Dada De Baja');
        $this->reporte(['tipo' => 'propiedades', 'estado' => 'BAJA'])->assertOk()->assertSee('Dada De Baja')->assertDontSee('Vigente Uno');
    }

    public function test_sin_resultados_muestra_el_mensaje_y_no_ofrece_exportar(): void
    {
        $this->actingAs($this->admin());

        $this->reporte(['tipo' => 'pagos'])->assertOk()->assertSee('Sin resultados para el reporte')
            ->assertDontSee('Exportar PDF')->assertDontSee('Exportar Excel');
        $this->get(route('reportes.exportar', ['formato' => 'pdf', 'tipo' => 'pagos']))
            ->assertRedirect(route('reportes.generar', ['tipo' => 'pagos']))->assertSessionHas('error', 'Sin resultados para el reporte');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_EXPORTADO')->count());
    }

    public function test_el_cliente_recibe_403_y_sin_sesion_va_al_login(): void
    {
        $this->get(route('reportes.index'))->assertRedirect(route('login'));
        $this->get(route('reportes.generar', ['tipo' => 'pagos']))->assertRedirect(route('login'));
        $this->get(route('reportes.exportar', ['formato' => 'pdf', 'tipo' => 'pagos']))->assertRedirect(route('login'));

        $this->actingAs($this->cliente());
        $this->get(route('reportes.index'))->assertForbidden();
        $this->reporte(['tipo' => 'pagos'])->assertForbidden();
        $this->get(route('reportes.exportar', ['formato' => 'xlsx', 'tipo' => 'pagos']))->assertForbidden();
    }

    public function test_valores_fuera_de_lista_o_mal_formados_dan_403_o_aviso_nunca_500(): void
    {
        $this->actingAs($this->admin());

        foreach ([['tipo' => 'inventado'], ['tipo' => ['x']], ['tipo' => 'pagos', 'estado' => 'RARO'], ['tipo' => 'contratos', 'agrupacion' => 'categoria'],
            ['tipo' => 'pagos', 'agrupacion' => 'nunca']] as $parametros) {
            $this->reporte($parametros)->assertForbidden()->assertSee('Este reporte no está permitido para tu rol.');
        }
        $this->reporte(['tipo' => 'pagos', 'idagente' => ['1'], 'zona' => ['z']])->assertOk();
        $this->reporte([])->assertOk()->assertSee('Elige el tipo de reporte.');
        $this->get('/reportes/exportar/docx?tipo=pagos')->assertNotFound();
    }

    public function test_agrupar_por_mes_estado_y_agente_y_filtrar_por_agente_zona_y_estado(): void
    {
        $admin = $this->admin();
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $beto = $this->agente(['nombre' => 'Beto Agente']);
        $zonaNorte = $this->propiedad(['titulo' => 'Casa Norte'], $ana);
        $zonaNorte->ubicacion->update(['zona' => 'Zona Norte']);
        $zonaSur = $this->propiedad(['titulo' => 'Casa Sur'], $beto);
        $zonaSur->ubicacion->update(['zona' => 'Zona Sur']);
        $this->visita($zonaNorte, '2026-11-10');
        $this->visita($zonaNorte, '2026-12-10', Visita::CANCELADA);
        $this->visita($zonaSur, '2026-12-11');
        $this->actingAs($admin);

        $this->reporte(['tipo' => 'visitas', 'agrupacion' => 'mes'])->assertOk()->assertSee('11/2026')->assertSee('12/2026');
        $this->reporte(['tipo' => 'visitas', 'agrupacion' => 'agente'])->assertOk()->assertSee('Ana Agente')->assertSee('Beto Agente');
        $this->reporte(['tipo' => 'visitas', 'agrupacion' => 'estado'])->assertOk()->assertSee('Programada')->assertSee('Cancelada');
        $this->reporte(['tipo' => 'visitas', 'zona' => 'Zona Sur'])->assertOk()->assertSee('Casa Sur')->assertDontSee('Casa Norte');
        $this->reporte(['tipo' => 'visitas', 'idagente' => (string) $ana->idusuario, 'estado' => 'PROGRAMADA'])->assertOk()
            ->assertSee('Casa Norte')->assertDontSee('Casa Sur')->assertSee('Agente: Ana Agente');
        $this->reporte(['tipo' => 'propiedades', 'zona' => 'Zona Norte'])->assertOk()->assertSee('Casa Norte')->assertDontSee('Casa Sur');
    }

    public function test_el_agente_no_puede_agrupar_por_agente_pero_si_ve_sus_propiedades_y_clientes(): void
    {
        $agente = $this->agente();
        $this->propiedad(['titulo' => 'Propia'], $agente);
        $this->propiedad(['titulo' => 'De Otro'], $this->agente());
        Cliente::create(['nombre' => 'Mi Prospecto', 'email' => 'mp@test.com', 'idusuario' => $agente->idusuario]);
        Cliente::create(['nombre' => 'Su Prospecto', 'email' => 'sp@test.com', 'idusuario' => $this->agente()->idusuario]);
        $this->actingAs($agente);

        $this->reporte(['tipo' => 'propiedades', 'agrupacion' => 'agente'])->assertForbidden();
        $this->reporte(['tipo' => 'propiedades'])->assertOk()->assertSee('Propia')->assertDontSee('De Otro');
        $this->reporte(['tipo' => 'clientes'])->assertOk()->assertSee('Mi Prospecto')->assertDontSee('Su Prospecto');
        $this->get(route('reportes.index'))->assertOk()->assertDontSee('value="agente"', false);
    }

    public function test_el_total_de_pagos_solo_suma_los_pagados(): void
    {
        $contrato = $this->contrato(1000, '2026-11-05');
        $this->pago($contrato, 100);
        $this->pago($contrato, 200, Pago::PENDIENTE);
        $this->pago($contrato, 400, Pago::ANULADO);
        $this->actingAs($this->admin());

        $this->reporte(['tipo' => 'pagos'])->assertOk()->assertSee('Total pagado (USD)')->assertSee('100.00')->assertDontSee('700.00');
        $this->reporte(['tipo' => 'pagos', 'estado' => 'PAGADO'])->assertOk()->assertSee('Total de filas');
    }

    public function test_el_panel_muestra_la_tarjeta_de_reportes_a_los_tres_roles_y_no_al_cliente(): void
    {
        foreach ([$this->admin(), $this->agente(), $this->asistente()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))->assertSee('Generar reportes con filtros y exportarlos a PDF o Excel');
        }
        $this->actingAs($this->cliente())->get(route('panel'))->assertDontSee('Generar reportes con filtros');
    }
}