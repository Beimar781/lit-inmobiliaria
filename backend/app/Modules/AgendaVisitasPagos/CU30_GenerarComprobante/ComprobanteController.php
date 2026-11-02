<?php

namespace App\Modules\AgendaVisitasPagos\CU30_GenerarComprobante;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\ComprobanteDePago;
use Illuminate\Http\Response;

/**
 * CU30: Generar comprobante de pago (Asistente Administrativo y Administrador)
 * Es el comprobante del PAGO FORMAL de un contrato (CP-AAAA-000001). Los recibos de la reserva (RV y DV) son otros documentos.
 * Se muestra automáticamente al registrar un pago (CU28) y se puede volver a emitir desde el pago (CU29).
 * Flujo principal:
 *  1. El actor selecciona el pago (o el CU28 invoca el CU30 al registrar un pago)    -> mostrar()
 *  2. El sistema obtiene los datos del pago, el contrato, la propiedad y el cliente
 *  3. Si el pago aún no tiene número de comprobante, el sistema lo genera y lo asigna -> ComprobanteDePago::asegurar()
 *  4. El sistema arma el comprobante con los datos obtenidos
 *  5. El sistema muestra el comprobante                                            -> comprobante.blade.php
 *  6. El actor lo imprime o lo guarda (botón "Imprimir o guardar como PDF")
 * Flujo alternativo: si el comprobante ya existía (reimpresión), se conserva el mismo número.
 * Excepciones: el pago no existe, está anulado o pendiente ("Comprobante no disponible"), datos incompletos, error de base de datos.
 */
class ComprobanteController extends Controller
{
    public function mostrar(ComprobanteDePago $comprobantes, string $pago): Response
    {
        $registro = ctype_digit($pago)
            ? Pago::with(['contrato.propiedad', 'contrato.cliente', 'tipopago', 'usuario'])->find($pago)
            : null;

        // Excepción: el pago no existe o está anulado (o todavía pendiente).
        if (! ComprobanteDePago::disponible($registro)) {
            return response()->view('agendavisitaspagos::CU30_GenerarComprobante.no-disponible', [
                'motivo' => $registro ? 'El pago está ' . strtolower($registro->estado_etiqueta) . '.' : 'El pago no existe.',
            ], 404);
        }

        // Paso 3: si todavía no tiene número, se genera; si ya lo tenía, se conserva.
        $registro = $comprobantes->asegurar($registro)->load(['contrato.propiedad', 'contrato.cliente', 'tipopago', 'usuario']);

        return response()->view('agendavisitaspagos::CU30_GenerarComprobante.comprobante', [
            'pago' => $registro,
            'contrato' => $registro->contrato,
            'saldoRestante' => ComprobanteDePago::saldoDespuesDelPagoUsd($registro),
        ]);
    }
}
