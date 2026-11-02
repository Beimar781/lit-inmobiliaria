<?php

namespace App\Modules\AgendaVisitasPagos\CU14_AgendarVisita;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\RegistroDeInteres;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * CU14: Agendar visita a inmueble (Cliente público, Agente inmobiliario y Administrador)
 * El cliente público agenda sin iniciar sesión desde la ficha de la propiedad (CU11). El Agente o el Administrador
 * pueden agendar en nombre de un cliente desde el inventario o desde un prospecto del CRM.
 * Flujo principal:
 *  1. El actor selecciona "Agendar visita" en la ficha de la propiedad      -> ficha del CU11 / inventario / prospecto
 *  2. El sistema muestra el formulario con los datos de la propiedad        -> crear()  (agendar.blade.php)
 *  3. El actor elige la fecha y la hora y, si es cliente público, ingresa su nombre, correo o teléfono y una observación opcional
 *  4. El actor confirma el registro                                          -> guardar()
 *  5. El sistema valida los datos (campos obligatorios y fecha futura) y que la propiedad siga disponible
 *                                                                            -> AgendarVisitaRequest / guardar()
 *  6. El sistema consulta el calendario del agente responsable y verifica que no tenga otra visita en ese horario
 *                                                                            -> AgendaDeVisitas::ocupado()
 *  7. El sistema registra al cliente (o reutiliza el existente) y guarda la visita como "Programada"
 *                                                                            -> RegistroDeInteres (CU12) / Visita::create()
 *  8. El sistema muestra la confirmación con el resumen de la visita        -> confirmacion()
 * Flujo alternativo: horario ocupado -> se informa y se vuelve al paso 3. Cliente existente (mismo correo o teléfono) -> se reutiliza.
 * Excepciones: propiedad inexistente o dada de baja (404), propiedad no disponible, fecha u hora pasada, campos incompletos,
 * horario ocupado, error de base de datos. Cancelar el proceso = volver a la ficha sin guardar nada.
 */
class AgendarVisitaController extends Controller
{
    public function crear(Request $request, Propiedad $propiedad, AgendaDeVisitas $agenda): View|RedirectResponse
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        if ($propiedad->estadopropiedad !== Propiedad::DISPONIBLE) {
            return $this->noDisponible($propiedad);
        }

        $personal = $this->esPersonal($request);

        return view('agendavisitaspagos::CU14_AgendarVisita.agendar', [
            'propiedad' => $propiedad->load(['categoria', 'ubicacion', 'agente']),
            'horas' => AgendaDeVisitas::horas(),
            'ocupadas' => $agenda->ocupadasProximas($propiedad),
            'minimo' => AgendaDeVisitas::ahora()->toDateString(),
            'personal' => $personal,
            'clientes' => $personal ? $this->clientesDelActor($request) : [],
            'clienteElegido' => $request->query('cliente'),
        ]);
    }

    public function guardar(AgendarVisitaRequest $request, Propiedad $propiedad, AgendaDeVisitas $agenda, RegistroDeInteres $registro): RedirectResponse
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        // Trampa para robots: el campo "sitio_web" está oculto para las personas. Si viene lleno, se finge éxito y no se guarda nada.
        if ($request->filled('sitio_web')) {
            return redirect()->route('catalogo.detalle', $propiedad)
                ->with('status', '¡Gracias! Tu solicitud de visita fue recibida.');
        }

        // Paso 5: la propiedad debe seguir disponible.
        if ($propiedad->estadopropiedad !== Propiedad::DISPONIBLE) {
            return $this->noDisponible($propiedad);
        }

        $actor = $request->user();
        $fecha = $request->input('fecha');
        $hora = AgendaDeVisitas::hora($request->input('hora'));
        $clienteCrm = null;

        // El personal puede elegir un cliente del CRM; el Agente solo entre sus prospectos.
        if ($request->esPersonal() && $request->filled('idcliente')) {
            $clienteCrm = Cliente::findOrFail($request->input('idcliente'));

            if ($actor->tieneRol(Rol::AGENTE) && $clienteCrm->idusuario !== $actor->idusuario) {
                return back()->withInput()->withErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);
            }
        }

        try {
            $resultado = DB::transaction(function () use ($propiedad, $agenda, $registro, $request, $actor, $fecha, $hora, $clienteCrm) {
                // Dos personas no pueden tomar el mismo horario a la vez: se bloquea al agente responsable
                // (o la propiedad, si no tiene agente) mientras se comprueba el horario y se guarda la visita.
                if ($propiedad->idusuario) {
                    Usuario::whereKey($propiedad->idusuario)->lockForUpdate()->first();
                }
                $actual = Propiedad::whereKey($propiedad->idpropiedad)->lockForUpdate()->first();

                if (! $actual || $actual->estadopropiedad !== Propiedad::DISPONIBLE) {
                    return ['no_disponible' => true];
                }

                // Paso 6: el agente responsable no puede tener otra visita en ese horario.
                if ($agenda->ocupado($actual, $fecha, $hora)) {
                    return ['ocupado' => true];
                }

                // Paso 7: se registra al cliente (o se reutiliza el existente) con la misma lógica del CU12.
                $mensaje = trim('Agendó una visita para el ' . date('d/m/Y', strtotime($fecha)) . ' a las ' . substr($hora, 0, 5) . '. ' . $request->input('observacion'));

                if ($clienteCrm) {
                    $cliente = $clienteCrm;
                    $registro->registrarInteres($cliente, $actual, $mensaje);
                } else {
                    $cliente = $registro->registrar(
                        array_merge($request->safe()->only(['nombre', 'email', 'telefono']), [
                            'tipocliente' => $actual->tipopropiedad === 'Venta' ? 'Comprador' : 'Inquilino',
                            'mensaje' => $mensaje,
                        ]),
                        $actual,
                        $actor?->tieneRol(Rol::AGENTE) ? $actor : null
                    )['cliente'];
                }

                $visita = Visita::create([
                    'idpropiedad' => $actual->idpropiedad,
                    'idcliente' => $cliente->idcliente,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'estado' => Visita::PROGRAMADA,
                    'observacion' => trim((string) $request->input('observacion')) ?: null,
                ]);

                return ['visita' => $visita, 'cliente' => $cliente, 'propiedad' => $actual];
            });
        } catch (QueryException $error) {
            // Excepción: error de conexión o de la base de datos.
            report($error);

            return back()->withInput()->with('error', 'No se pudo registrar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['no_disponible'])) {
            return $this->noDisponible($propiedad);
        }

        // Flujo alternativo: horario ocupado -> se informa y se ofrece elegir otro (vuelve al paso 3).
        if (isset($resultado['ocupado'])) {
            $libres = $agenda->horasLibres($propiedad, $fecha);
            $sugerencia = $libres
                ? ' Horarios libres ese día: ' . implode(', ', $libres) . '.'
                : ' Ese día ya no quedan horarios libres: elige otra fecha.';

            return back()->withInput()->withErrors(['hora' => 'Ese horario ya está ocupado.' . $sugerencia]);
        }

        /** @var Visita $visita */
        $visita = $resultado['visita'];
        $cliente = $resultado['cliente'];
        $propiedad = $resultado['propiedad'];

        BitacoraService::registrar('VISITA_PROGRAMADA', 'Agenda', "Agendó la visita de {$cliente->nombre} a «{$propiedad->titulo}» ({$propiedad->codigo}) para el " . date('d/m/Y', strtotime($fecha)) . ' a las ' . substr($hora, 0, 5), [
            'idvisita' => $visita->idvisita,
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'idagente' => $propiedad->idusuario,
            'fecha' => $fecha,
            'hora' => substr($hora, 0, 5),
            'canal' => $actor?->tieneRol(Rol::ADMINISTRADOR, Rol::AGENTE) ? 'personal' : 'formulario público',
        ]);

        // El enlace de la confirmación va firmado: así nadie puede ver las visitas de otros cambiando el número.
        return redirect(URL::temporarySignedRoute('visitas.confirmacion', now()->addDays(2), ['visita' => $visita->idvisita]));
    }

    /** Paso 8: confirmación con el resumen de la visita. */
    public function confirmacion(Request $request, Visita $visita): View
    {
        $visita->load(['propiedad.agente', 'propiedad.ubicacion', 'cliente']);

        return view('agendavisitaspagos::CU14_AgendarVisita.confirmacion', [
            'visita' => $visita,
            'personal' => $this->esPersonal($request),
        ]);
    }

    private function esPersonal(Request $request): bool
    {
        return (bool) $request->user()?->tieneRol(Rol::ADMINISTRADOR, Rol::AGENTE);
    }

    /** Prospectos que el actor puede elegir: el Agente los suyos; el Administrador todos. */
    private function clientesDelActor(Request $request): array
    {
        $actor = $request->user();

        return Cliente::query()
            ->when($actor->tieneRol(Rol::AGENTE), fn ($c) => $c->where('idusuario', $actor->idusuario))
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Cliente $c) => [$c->idcliente => $c->nombre . ($c->email ? " · {$c->email}" : ($c->telefono ? " · {$c->telefono}" : ''))])
            ->all();
    }

    private function noDisponible(Propiedad $propiedad): RedirectResponse
    {
        return redirect()->route('catalogo.detalle', $propiedad)->with('error', 'Esta propiedad ya no está disponible.');
    }
}
