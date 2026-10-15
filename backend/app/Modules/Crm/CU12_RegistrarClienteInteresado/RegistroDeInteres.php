<?php

namespace App\Modules\Crm\CU12_RegistrarClienteInteresado;

use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Illuminate\Support\Facades\DB;

/**
 * Lógica del CU12 (la usan el formulario público "Me interesa" y el formulario del agente):
 *  - existeCliente():       ¿ya hay un cliente con ese correo o teléfono?
 *  - agenteAsignado():      el agente de la propiedad (si lo hay)
 *  - registrarCliente():    crea el cliente (o completa el que ya existía)
 *  - registrarInteres():    vincula al cliente con la propiedad, sin duplicar
 */
class RegistroDeInteres
{
    /**
     * @param array{nombre: string, email?: ?string, telefono?: ?string, tipocliente?: ?string, mensaje?: ?string} $datos
     * @param Usuario|null $agenteQueRegistra  el agente que ingresa los datos (null si fue el propio cliente público)
     * @return array{cliente: Cliente, interes: Interes, cliente_nuevo: bool, ya_registrado: bool}
     */
    public function registrar(array $datos, Propiedad $propiedad, ?Usuario $agenteQueRegistra = null): array
    {
        $datos = $this->normalizar($datos);

        $resultado = DB::transaction(function () use ($datos, $propiedad, $agenteQueRegistra) {
            $existente = $this->existeCliente($datos['email'], $datos['telefono']);
            $agenteId = $this->agenteAsignado($propiedad) ?? $agenteQueRegistra?->idusuario;

            $cliente = $this->registrarCliente($datos, $agenteId, $existente);
            [$interes, $yaRegistrado] = $this->registrarInteres($cliente, $propiedad, $datos['mensaje'] ?? null);

            return [
                'cliente' => $cliente,
                'interes' => $interes,
                'cliente_nuevo' => $existente === null,
                'ya_registrado' => $yaRegistrado,
            ];
        });

        // La bitácora va fuera de la transacción: si fallara, no debe deshacer el registro ya hecho.
        if (! $resultado['ya_registrado']) {
            $cliente = $resultado['cliente'];

            BitacoraService::registrar('INTERES_REGISTRADO', 'CRM', "Registró el interés de {$cliente->nombre} por la propiedad «{$propiedad->titulo}» ({$propiedad->codigo})", [
                'idcliente' => $cliente->idcliente,
                'idpropiedad' => $propiedad->idpropiedad,
                'cliente_nuevo' => $resultado['cliente_nuevo'],
                'canal' => $agenteQueRegistra ? 'agente' : 'formulario público',
                'idagente' => $cliente->idusuario,
            ]);
        }

        return $resultado;
    }

    /** Busca a un cliente con el mismo correo o el mismo teléfono. */
    public function existeCliente(?string $correo, ?string $telefono): ?Cliente
    {
        if (! $correo && ! $telefono) {
            return null;
        }

        return Cliente::query()
            ->where(function ($donde) use ($correo, $telefono) {
                if ($correo) {
                    $donde->orWhere('email', $correo);
                }
                if ($telefono) {
                    $donde->orWhere('telefono', $telefono);
                }
            })
            ->orderBy('idcliente')
            ->first();
    }

    /** El agente de la propiedad, si existe y su cuenta está activa. Si no, el prospecto queda sin asignar. */
    public function agenteAsignado(Propiedad $propiedad): ?int
    {
        $agente = $propiedad->idusuario ? Usuario::find($propiedad->idusuario) : null;

        return $agente?->estaActivo() ? $agente->idusuario : null;
    }

    /** Crea el cliente nuevo con estado "Nuevo"; si ya existía, conserva su estado y completa lo que le faltaba. */
    public function registrarCliente(array $datos, ?int $agenteId, ?Cliente $existente = null): Cliente
    {
        if ($existente === null) {
            return Cliente::create([
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                'telefono' => $datos['telefono'],
                'tipocliente' => $datos['tipocliente'] ?? null,
                'estadoseguimiento' => Cliente::NUEVO,
                'idusuario' => $agenteId,
            ]);
        }

        $existente->fill(array_filter([
            'email' => $existente->email ?: $datos['email'],
            'telefono' => $existente->telefono ?: $datos['telefono'],
            'tipocliente' => $existente->tipocliente ?: ($datos['tipocliente'] ?? null),
            'idusuario' => $existente->idusuario ?: $agenteId,
        ]));
        $existente->save();

        return $existente;
    }

    /** @return array{0: Interes, 1: bool}  [interés, ya estaba registrado] */
    public function registrarInteres(Cliente $cliente, Propiedad $propiedad, ?string $mensaje): array
    {
        // createOrFirst se apoya en el índice único (propiedad, cliente): un doble clic en "Me interesa" no da error ni duplica.
        $interes = Interes::createOrFirst(
            ['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente],
            ['mensaje' => $mensaje, 'fecha' => now()->toDateString()]
        );

        return [$interes, ! $interes->wasRecentlyCreated];
    }

    /**
     * Correo en minúsculas y teléfono solo con dígitos, para poder comparar.
     * El prefijo de Bolivia (+591, 591 o 00591) se quita: "71234567" y "+591 71234567" son el mismo teléfono.
     */
    private function normalizar(array $datos): array
    {
        $correo = isset($datos['email']) ? mb_strtolower(trim($datos['email'])) : null;
        $telefono = isset($datos['telefono']) ? preg_replace('/^(?:00)?591(?=\d{8}$)/', '', preg_replace('/\D+/', '', $datos['telefono'])) : null;
        $mensaje = isset($datos['mensaje']) ? trim($datos['mensaje']) : null;

        return [
            'nombre' => trim($datos['nombre']),
            'email' => $correo ?: null,
            'telefono' => $telefono ?: null,
            'tipocliente' => $datos['tipocliente'] ?? null,
            'mensaje' => $mensaje ?: null,
        ];
    }
}
