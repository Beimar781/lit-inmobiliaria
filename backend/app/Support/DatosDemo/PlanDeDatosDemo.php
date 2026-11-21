<?php

namespace App\Support\DatosDemo;

use App\Modules\GestionPropiedades\Compartido\LimiteCuartoAnillo;
use Carbon\CarbonImmutable;

/**
 * Plan de los datos ficticios (demo) para probar los reportes: qué filas crear y cómo se relacionan, sin ids.
 * Es determinista (semilla fija): siempre da lo mismo. Devuelve una lista de operaciones que dos escritores consumen:
 * uno inserta en la base y otro genera el script SQL para otra base (Railway), así los dos quedan iguales.
 *
 * Los escenarios (reserva -> contrato -> pagos -> reseña) respetan las reglas reales del sistema: la propiedad cambia de estado
 * como lo haría CU26/CU27, la devolución de una reserva retiene el 20 %, los pagos PAGADO no superan el contrato, el alquiler
 * se paga por meses y los comprobantes siguen la numeración real. Todas las fechas de creación están en el pasado.
 *
 * Cada operación: ['tabla' => ..., 'fila' => [columna => valor|Ref], 'clave' => ?array, 'visita' => ?array].
 */
final class PlanDeDatosDemo
{
    public const TIPO_CAMBIO = 6.96;
    public const DIAS_VENTANA = 123; // del 21/07 al 21/11

    private Azar $azar;
    private CarbonImmutable $hoy;

    /** @var array<int, array> */
    private array $ops = [];
    /** @var array<int, array> propiedades por número (1 a 14) */
    private array $props = [];
    /** @var array<int, array> clientes por posición */
    private array $clientes = [];
    /** @var array<string, bool> visitas ya ocupadas: agente|día|hora */
    private array $ocupadas = [];
    /** @var array<string, bool> fechas de pago usadas por propiedad */
    private array $fechasPago = [];

    public function __construct(CarbonImmutable $hoy, int $semilla = 2026)
    {
        $this->hoy = $hoy->startOfDay();
        $this->azar = new Azar($semilla);
    }

    /** @return array<int, array{tabla: string, fila: array, clave: ?array, visita: ?array}> */
    public function construir(): array
    {
        $this->ops = [];
        $this->props = [];
        $this->clientes = [];
        $this->ocupadas = [];
        $this->fechasPago = [];

        $this->propietarios();
        $this->propiedades();
        $this->clientes();
        $this->negocio();
        $this->intereses();
        $this->seguimientos();
        $this->inquilinos();
        $this->visitas();
        $this->resenas();

        return $this->ops;
    }

    // ----------------------------------------------------------------------------------------------- utilidades

    private function agregar(string $tabla, array $fila, ?array $clave = null, ?array $visita = null): void
    {
        $this->ops[] = compact('tabla', 'fila', 'clave', 'visita');
    }

    /** Fecha y hora de hace $atras días (negativo = futuro), con hora de oficina al azar si no se indica. */
    private function dia(int $atras, ?int $hora = null): string
    {
        return $this->hoy->subDays($atras)
            ->setTime($hora ?? $this->azar->entero(8, 17), $this->azar->entero(0, 59), $this->azar->entero(0, 59))
            ->format('Y-m-d H:i:s');
    }

    private function fecha(int $atras): string
    {
        return $this->hoy->subDays($atras)->format('Y-m-d');
    }

    private function propiedad(int $n): Ref
    {
        return Ref::fila('propiedad', ['titulo' => $this->props[$n]['titulo']]);
    }

    private function cliente(int $i): Ref
    {
        return Ref::fila('cliente', ['email' => $this->clientes[$i]['email']]);
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------------------------------- propietarios y propiedades

    private function propietarios(): void
    {
        $lista = [
            ['DEMO - Familia Rojas Cuéllar', '77012345', 'rojas.cuellar@demo.lit.test', 'Calle Ñuflo de Chávez 340, Santa Cruz de la Sierra'],
            ['DEMO - Inversiones Oriente S.R.L.', '33456789', 'contacto.oriente@demo.lit.test', 'Av. Cristo Redentor 1520, Santa Cruz de la Sierra'],
            ['DEMO - Marcelo Ibáñez Parada', '76098765', 'marcelo.ibanez@demo.lit.test', 'Calle Los Pinos 87, Santa Cruz de la Sierra'],
        ];

        foreach ($lista as [$nombre, $telefono, $email, $direccion]) {
            $this->agregar('propietario', compact('nombre', 'telefono', 'email', 'direccion'), ['nombre' => $nombre]);
        }
    }

    private function propiedades(): void
    {
        $propietarios = ['DEMO - Familia Rojas Cuéllar', 'DEMO - Inversiones Oriente S.R.L.', 'DEMO - Marcelo Ibáñez Parada'];
        // Puntos de referencia dentro del 4.º anillo (zona => [latitud, longitud]); a cada propiedad se le suma un pequeño desvío.
        $anclas = [
            'Equipetrol' => [-17.7630, -63.1980], 'Equipetrol Norte' => [-17.7550, -63.1900], 'Zona Norte' => [-17.7523, -63.1735],
            'Urbarí' => [-17.7976, -63.1973], 'Sirari' => [-17.7680, -63.1860], 'Las Palmas' => [-17.8009, -63.1962],
            'Centro' => [-17.7850, -63.1790], 'Hamacas' => [-17.8100, -63.1900], 'Zona Sur' => [-17.8150, -63.1750],
            'Zona Este' => [-17.7830, -63.1500], 'Barrio Lindo' => [-17.7790, -63.1650],
        ];

        // n => [título, categoría, tipo, zona, calle, precio, superficie, construida, hab., baños, antigüedad, agente, días desde el registro, propietario, estado final]
        $catalogo = [
            1 => ['Casa de 4 dormitorios con jardín en Equipetrol Norte', 'Casa', 'Venta', 'Equipetrol Norte', 'Calle Los Cedros #412', 135000, 420, 280, 4, 3, 6, 1, 118, 0, 'VENDIDO'],
            2 => ['Departamento de 3 dormitorios con vista en Equipetrol', 'Departamento', 'Venta', 'Equipetrol', 'Av. San Martín #1180', 98000, 160, 160, 3, 2, 4, 2, 112, 1, 'VENDIDO'],
            3 => ['Terreno de 600 m² en Zona Norte', 'Terreno', 'Venta', 'Zona Norte', 'Calle 7 Norte #95', 48000, 600, null, null, null, null, 3, 116, 2, 'VENDIDO'],
            4 => ['Casa en condominio cerrado de Las Palmas', 'Casa', 'Venta', 'Las Palmas', 'Condominio Las Palmas, lote 14', 172000, 380, 260, 4, 3, 3, 1, 95, 0, 'RESERVADO'],
            5 => ['Local comercial esquinero sobre el segundo anillo', 'Local Comercial', 'Venta', 'Centro', 'Calle Murillo esq. Libertad #230', 89000, 120, 110, null, 2, 8, 4, 88, 1, 'RESERVADO'],
            6 => ['Casa de 3 dormitorios cerca del Urbarí', 'Casa', 'Venta', 'Urbarí', 'Calle Los Tajibos #66', 118000, 300, 190, 3, 2, 10, 2, 84, 2, 'DISPONIBLE'],
            7 => ['Oficina de 90 m² en torre empresarial', 'Oficina', 'Venta', 'Sirari', 'Av. Beni, Torre Sirari piso 6', 64000, 90, 90, null, 2, 5, 5, 79, 0, 'DISPONIBLE'],
            8 => ['Departamento de 2 dormitorios en Hamacas', 'Departamento', 'Venta', 'Hamacas', 'Calle Las Hamacas #520', 76000, 110, 110, 2, 2, 7, 3, 100, 1, 'DISPONIBLE'],
            9 => ['Terreno de 450 m² en Zona Sur', 'Terreno', 'Venta', 'Zona Sur', 'Calle Cañada Strongest #31', 39000, 450, null, null, null, null, 1, 105, 2, 'DISPONIBLE'],
            10 => ['Departamento amoblado en alquiler, Barrio Lindo', 'Departamento', 'Alquiler', 'Barrio Lindo', 'Calle Barrio Lindo #208', 550, 85, 85, 2, 1, 2, 4, 114, 0, 'DISPONIBLE'],
            11 => ['Departamento de 2 dormitorios en alquiler, Equipetrol', 'Departamento', 'Alquiler', 'Equipetrol', 'Calle Los Jazmines #74', 600, 95, 95, 2, 2, 3, 2, 108, 1, 'ALQUILADO'],
            12 => ['Oficina en alquiler en el centro', 'Oficina', 'Alquiler', 'Centro', 'Calle Bolívar #355, 2.º piso', 450, 70, 70, null, 1, 12, 3, 70, 2, 'ALQUILADO'],
            13 => ['Local comercial en alquiler en Las Palmas', 'Local Comercial', 'Alquiler', 'Las Palmas', 'Av. Las Palmas #901', 500, 60, 60, null, 1, 6, 1, 121, 0, 'DISPONIBLE'],
            14 => ['Casa en anticrético en Zona Este', 'Casa', 'Anticrético', 'Zona Este', 'Calle Santos Dumont #144', 30000, 250, 170, 3, 2, 9, 5, 68, 1, 'ALQUILADO'],
        ];

        $detalle = [
            'Casa' => 'Casa luminosa con cochera techada, patio y cocina equipada. Barrio tranquilo y con buen acceso a avenidas principales.',
            'Departamento' => 'Departamento con buena ventilación, cocina integrada, lavandería y seguridad las 24 horas en el edificio.',
            'Terreno' => 'Terreno plano, con todos los servicios básicos y papeles al día. Ideal para vivienda o inversión.',
            'Oficina' => 'Oficina con recepción, aire acondicionado y estacionamiento para clientes. Edificio con ascensor y vigilancia.',
            'Local Comercial' => 'Local con frente amplio, buena visibilidad y alto tránsito peatonal. Baño y depósito incluidos.',
        ];

        foreach ($catalogo as $n => [$titulo, $categoria, $tipo, $zona, $calle, $precio, $sup, $const, $hab, $banos, $ant, $slot, $dias, $dueno, $estado]) {
            [$lat, $lng] = $this->punto($anclas[$zona]);
            $direccion = $calle . ' (referencial)';
            $registro = $this->dia($dias);

            $this->props[$n] = compact('titulo', 'categoria', 'tipo', 'zona', 'precio', 'slot', 'dias', 'estado', 'direccion') + ['registro' => $registro];

            $this->agregar('ubicacion', [
                'ciudad' => config('santacruz.ciudad'), 'zona' => $zona, 'direccion' => $direccion,
                'latitud' => $lat, 'longitud' => $lng,
            ], ['direccion' => $direccion]);

            $this->agregar('propiedad', [
                'idpropietario' => Ref::fila('propietario', ['nombre' => $propietarios[$dueno]]),
                'idcategoria' => Ref::categoria($categoria),
                'idubicacion' => Ref::fila('ubicacion', ['direccion' => $direccion]),
                'idusuario' => Ref::agente($slot),
                'titulo' => $titulo,
                'descripcion' => $detalle[$categoria],
                'precio' => $precio,
                'tipopropiedad' => $tipo,
                'estadopropiedad' => $estado,
                'superficie' => $sup,
                'areaconstruida' => $const,
                'habitaciones' => $hab,
                'banos' => $banos,
                'antiguedad' => $ant,
                'sector' => $zona,
                'fecharegistro' => $registro,
            ], ['titulo' => $titulo]);

            $this->agregar('historial', [
                'tipo' => 'REGISTRO', 'valoranterior' => null,
                'valoractual' => $this->json(['titulo' => $titulo, 'precio' => number_format($precio, 2, '.', ''), 'tipo' => $tipo, 'estado' => 'DISPONIBLE', 'zona' => $zona, 'direccion' => $direccion]),
                'motivo' => null, 'fecha' => $registro, 'idusuario' => Ref::agente($slot), 'idpropiedad' => $this->propiedad($n),
            ]);
        }

        // Algunas propiedades bajaron de precio (se ve en el historial del CU31): [propiedad, precio anterior, días atrás].
        foreach ([[1, 140000, 105], [4, 178000, 60], [6, 125000, 70], [8, 79000, 90], [14, 32000, 55]] as [$n, $anterior, $dias]) {
            $p = $this->props[$n];
            $this->agregar('historial', [
                'tipo' => 'MODIFICACION',
                'valoranterior' => $this->json(['precio' => number_format($anterior, 2, '.', '')]),
                'valoractual' => $this->json(['precio' => number_format($p['precio'], 2, '.', '')]),
                'motivo' => null, 'fecha' => $this->dia($dias), 'idusuario' => Ref::agente($p['slot']), 'idpropiedad' => $this->propiedad($n),
            ]);
        }
    }

    /** Punto cerca de la referencia, siempre dentro del 4.º anillo. @return array{0: string, 1: string} */
    private function punto(array $ancla): array
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $lat = $ancla[0] + $this->azar->entero(-35, 35) / 10000;
            $lng = $ancla[1] + $this->azar->entero(-35, 35) / 10000;
            if (LimiteCuartoAnillo::contiene($lat, $lng)) {
                return [number_format($lat, 7, '.', ''), number_format($lng, 7, '.', '')];
            }
        }

        return [number_format($ancla[0], 7, '.', ''), number_format($ancla[1], 7, '.', '')];
    }

    // ------------------------------------------------------------------------------------------------ clientes

    private function clientes(): void
    {
        $nombres = ['Rodrigo', 'Daniela', 'Marcelo', 'Valeria', 'Gustavo', 'Andrea', 'Fernando', 'Lucía', 'Sergio', 'Camila', 'Mauricio', 'Paola',
            'Álvaro', 'Natalia', 'Ricardo', 'Karen', 'Hernán', 'Mónica', 'Jorge', 'Silvia', 'Cristian', 'Patricia', 'Oscar', 'Verónica', 'Ramiro',
            'Claudia', 'Iván', 'Fabiola', 'Wilson', 'Rosario', 'Nelson', 'Alejandra', 'Eduardo', 'Tatiana', 'Pablo', 'Gabriela'];
        $apellidos = ['Chávez', 'Montaño', 'Suárez', 'Rojas', 'Vargas', 'Flores', 'Mamani', 'Quispe', 'Cuéllar', 'Peña', 'Salvatierra', 'Ibáñez',
            'Parada', 'Antelo', 'Roca', 'Justiniano', 'Soliz', 'Barba', 'Gutiérrez', 'Cortez', 'Egüez', 'Banzer', 'Moreno', 'Hurtado', 'Villarroel',
            'Zeballos', 'Ribera', 'Arteaga', 'Saucedo', 'Terrazas', 'Guzmán', 'Paz', 'Velasco', 'Añez', 'Durán', 'Claure'];

        // Los 14 primeros clientes protagonizan los escenarios de negocio: [tipo, estado de seguimiento, primer evento (días atrás), propiedad].
        $roles = [
            ['Comprador', 'CERRADO', 92, 1], ['Comprador', 'CERRADO', 52, 2], ['Comprador', 'CERRADO', 104, 3], ['Comprador', 'EN_NEGOCIACION', 6, 4],
            ['Comprador', 'EN_NEGOCIACION', 3, 5], ['Comprador', 'DESCARTADO', 40, 6], ['Comprador', 'DESCARTADO', 30, 7], ['Comprador', 'DESCARTADO', 75, 8],
            ['Comprador', 'DESCARTADO', 92, 9], ['Inquilino', 'DESCARTADO', 104, 10], ['Inquilino', 'CERRADO', 99, 11], ['Inquilino', 'CERRADO', 65, 12],
            ['Inquilino', 'CERRADO', 112, 13], ['Inversionista', 'CERRADO', 56, 14],
        ];

        $total = 36;
        for ($i = 0; $i < $total; $i++) {
            $apellido = $apellidos[($i * 5 + 2) % 36];
            $nombre = $nombres[$i] . ' ' . $apellido;
            $correo = $this->correo($nombres[$i], $apellido);

            if ($i < count($roles)) {
                [$tipo, $estado, $evento, $prop] = $roles[$i];
                $slot = $this->props[$prop]['slot'];
                $registro = min($evento + $this->azar->entero(4, 14), self::DIAS_VENTANA - 1);
            } else {
                $prop = null;
                // Los cuatro siguientes serán inquilinos con su ficha (CU24); el resto, prospectos variados.
                $tipo = $i < 18 ? 'Inquilino' : $this->azar->ponderado(['Comprador' => 55, 'Inversionista' => 20, 'Inquilino' => 25]);
                $estado = $this->azar->ponderado(['NUEVO' => 35, 'CONTACTADO' => 35, 'EN_NEGOCIACION' => 20, 'DESCARTADO' => 10]);
                $slot = (int) $this->azar->ponderado([1 => 30, 2 => 24, 3 => 20, 4 => 16, 5 => 10]);
                $registro = $this->azar->entero(2, self::DIAS_VENTANA - 1);
            }

            $this->clientes[$i] = ['nombre' => $nombre, 'email' => $correo, 'tipo' => $tipo, 'estado' => $estado, 'slot' => $slot, 'registro' => $registro, 'prop' => $prop];

            $this->agregar('cliente', [
                'nombre' => $nombre,
                'email' => $correo,
                'telefono' => '7' . $this->azar->entero(1000000, 9999999),
                'tipocliente' => $tipo,
                'estadoseguimiento' => $estado,
                'idusuario' => Ref::agente($slot),
                'fecharegistro' => $this->dia($registro),
                'estado' => $estado === 'DESCARTADO' && $this->azar->probabilidad(70) ? 'inactivo' : 'activo',
            ], ['email' => $correo]);
        }
    }

    private function correo(string $nombre, string $apellido): string
    {
        $limpio = static function (string $texto): string {
            $sin = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', 'Ü' => 'U']);

            return strtolower(preg_replace('/[^A-Za-z]/', '', $sin));
        };

        return $limpio($nombre) . '.' . $limpio($apellido) . MarcasDemo::DOMINIO_CLIENTE;
    }

    // -------------------------------------------------------------------- reservas, contratos, pagos y estados

    private function negocio(): void
    {
        $meses = fn (int $dia, int $n) => $this->hoy->subDays($dia)->addMonthsNoOverflow($n)->format('Y-m-d');

        // n => [cliente, reserva, contrato, pagos [día, monto, moneda, estado]]
        $escenarios = [
            1 => [0, ['dia' => 92, 'monto' => 2700, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 77],
                ['dia' => 85, 'tipo' => 'Venta', 'monto' => 130000, 'estado' => 'FINALIZADO', 'fin' => 62],
                [[80, 57300, 'USD', 'PAGADO'], [62, 70000, 'USD', 'PAGADO']]],
            2 => [1, ['dia' => 52, 'monto' => 1960, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 37],
                ['dia' => 45, 'tipo' => 'Venta', 'monto' => 95000, 'estado' => 'VIGENTE'],
                [[38, 125280, 'BOB', 'PAGADO'], [30, 5000, 'USD', 'ANULADO'], [20, 9000, 'USD', 'PAGADO'], [3, 10000, 'USD', 'PENDIENTE']]],
            3 => [2, ['dia' => 104, 'monto' => 6681.60, 'moneda' => 'BOB', 'estado' => 'CONCRETADA', 'plazo' => 89],
                ['dia' => 100, 'tipo' => 'Venta', 'monto' => 47000, 'estado' => 'FINALIZADO', 'fin' => 70],
                [[97, 20000, 'USD', 'PAGADO'], [70, 26040, 'USD', 'PAGADO']]],
            4 => [3, ['dia' => 6, 'monto' => 12000, 'moneda' => 'BOB', 'estado' => 'VIGENTE', 'plazo' => -9], null, []],
            5 => [4, ['dia' => 3, 'monto' => 1800, 'moneda' => 'USD', 'estado' => 'VIGENTE', 'plazo' => -12], null, []],
            6 => [5, ['dia' => 40, 'monto' => 2400, 'moneda' => 'USD', 'estado' => 'VENCIDA', 'plazo' => 25, 'devolucion' => 24], null, []],
            7 => [6, ['dia' => 30, 'monto' => 1300, 'moneda' => 'USD', 'estado' => 'CANCELADA', 'plazo' => 15, 'cancelada' => 22], null, []],
            8 => [7, ['dia' => 75, 'monto' => 1520, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 60],
                ['dia' => 70, 'tipo' => 'Venta', 'monto' => 74000, 'estado' => 'RESCINDIDO', 'fin' => 58],
                [[62, 5000, 'USD', 'ANULADO']]],
            9 => [8, ['dia' => 92, 'monto' => 800, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 77],
                ['dia' => 88, 'tipo' => 'Venta', 'monto' => 38000, 'estado' => 'RESCINDIDO', 'fin' => 76],
                [[80, 3000, 'USD', 'PENDIENTE']]],
            10 => [9, null, ['dia' => 104, 'tipo' => 'Alquiler', 'monto' => 3300, 'estado' => 'RESCINDIDO', 'meses' => 6, 'fin' => 82],
                [[104, 550, 'USD', 'PAGADO'], [80, 550, 'USD', 'ANULADO']]],
            11 => [10, ['dia' => 99, 'monto' => 300, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 84],
                ['dia' => 95, 'tipo' => 'Alquiler', 'monto' => 7200, 'estado' => 'VIGENTE', 'meses' => 12],
                [[90, 300, 'USD', 'PAGADO'], [64, 600, 'USD', 'PAGADO'], [34, 600, 'USD', 'PAGADO'], [4, 600, 'USD', 'PENDIENTE']]],
            12 => [11, null, ['dia' => 65, 'tipo' => 'Alquiler', 'monto' => 2700, 'estado' => 'VIGENTE', 'meses' => 6],
                [[65, 450, 'USD', 'PAGADO'], [35, 3132, 'BOB', 'PAGADO'], [34, 450, 'USD', 'ANULADO'], [5, 450, 'USD', 'PAGADO']]],
            13 => [12, null, ['dia' => 112, 'tipo' => 'Alquiler', 'monto' => 1500, 'estado' => 'FINALIZADO', 'meses' => 3, 'fin' => 21, 'liberada' => 20],
                [[112, 500, 'USD', 'PAGADO'], [81, 500, 'USD', 'PAGADO'], [50, 500, 'USD', 'PAGADO']]],
            14 => [13, ['dia' => 56, 'monto' => 600, 'moneda' => 'USD', 'estado' => 'CONCRETADA', 'plazo' => 41],
                ['dia' => 50, 'tipo' => 'Anticrético', 'monto' => 28000, 'estado' => 'VIGENTE', 'meses' => 24],
                [[44, 16000, 'USD', 'PAGADO'], [15, 41760, 'BOB', 'PAGADO']]],
        ];

        $movimientos = [];
        $pagos = [];

        foreach ($escenarios as $n => [$i, $reserva, $contrato, $listaPagos]) {
            $p = $this->props[$n];
            $c = $this->clientes[$i];
            $clave = ['cliente' => $c['email'], 'propiedad' => $p['titulo']];
            $agente = Ref::agente($p['slot']);

            if ($reserva) {
                $usd = $reserva['moneda'] === 'BOB' ? round($reserva['monto'] / self::TIPO_CAMBIO, 2) : round($reserva['monto'], 2);
                $this->agregar('reserva', [
                    'idcliente' => $this->cliente($i), 'idpropiedad' => $this->propiedad($n),
                    'fechareserva' => $this->fecha($reserva['dia']), 'fechaplazo' => $this->fecha($reserva['plazo']),
                    'estado' => $reserva['estado'], 'idusuario' => $agente, 'montousd' => $usd,
                    'detalles' => 'Reserva con anticipo en efectivo, a cuenta del precio.',
                ], $clave);

                $fechaCobro = $this->dia($reserva['dia']);
                $movimientos[] = [$fechaCobro, $this->movimiento($clave, 'COBRO', $reserva['monto'], $reserva['moneda'], $usd, null, $fechaCobro, $agente)];
                $this->estado($n, $fechaCobro, 'DISPONIBLE', 'RESERVADO', 'Reserva registrada');

                if (isset($reserva['devolucion'])) {
                    $fechaDev = $this->dia($reserva['devolucion']);
                    $movimientos[] = [$fechaDev, $this->movimiento($clave, 'DEVOLUCION', round($reserva['monto'] * 0.8, 2), $reserva['moneda'], round($usd * 0.8, 2), 20, $fechaDev, $agente)];
                }
                if ($reserva['estado'] === 'VENCIDA') {
                    $this->estado($n, $this->dia($reserva['plazo'] - 1), 'RESERVADO', 'DISPONIBLE', 'La reserva venció sin formalizarse');
                }
                if ($reserva['estado'] === 'CANCELADA') {
                    $this->estado($n, $this->dia($reserva['cancelada']), 'RESERVADO', 'DISPONIBLE', 'Reserva cancelada');
                }
            }

            if ($contrato) {
                $inicio = $contrato['dia'];
                // Alquiler y anticrético tienen fecha de fin pactada (no cambia si luego se rescinde); la venta no.
                $fin = isset($contrato['meses']) ? $meses($inicio, $contrato['meses']) : null;
                $this->agregar('contrato', [
                    'idpropiedad' => $this->propiedad($n), 'idcliente' => $this->cliente($i), 'idusuario' => $agente,
                    'idreserva' => $reserva ? Ref::fila('reserva', $clave) : null,
                    'tipocontrato' => $contrato['tipo'], 'monto' => $contrato['monto'],
                    'fechainicio' => $this->fecha($inicio), 'fechafin' => $fin, 'estado' => $contrato['estado'],
                ], $clave);

                $nuevo = $contrato['tipo'] === 'Venta' ? 'VENDIDO' : 'ALQUILADO';
                $this->estado($n, $this->dia($inicio, 17), $reserva ? 'RESERVADO' : 'DISPONIBLE', $nuevo, 'Contrato registrado');
                if ($contrato['estado'] === 'RESCINDIDO') {
                    $this->estado($n, $this->dia($contrato['fin'], 17), $nuevo, 'DISPONIBLE', 'Contrato rescindido: el cliente desistió de la operación');
                }
                if (isset($contrato['liberada'])) {
                    $this->estado($n, $this->dia($contrato['liberada'], 17), $nuevo, 'DISPONIBLE', 'Contrato finalizado: el inmueble volvió a estar disponible');
                }

                // El dinero de la reserva formalizada pasa a ser el primer pago del contrato (mismo monto y moneda).
                if ($reserva && $reserva['estado'] === 'CONCRETADA') {
                    array_unshift($listaPagos, [$inicio, $reserva['monto'], $reserva['moneda'], 'PAGADO']);
                }

                foreach ($listaPagos as $datos) {
                    $pagos[] = $this->pago($clave, $datos, $p['titulo']);
                }
            }
        }

        // En orden de fecha, para que los comprobantes (RV, DV y CP) crezcan con el tiempo como en la vida real.
        usort($movimientos, fn ($a, $b) => strcmp($a[0], $b[0]));
        foreach ($movimientos as [, $op]) {
            $this->ops[] = $op;
        }

        usort($pagos, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));
        foreach ($pagos as $pago) {
            $this->agregar('pago', $pago['fila'], $pago['clave']);
            if ($pago['moneda'] === 'BOB') {
                $this->agregar('tipopago', [
                    'idpago' => Ref::fila('pago', $pago['clave']), 'cambiodemonedalocal' => self::TIPO_CAMBIO, 'fecha' => substr($pago['fecha'], 0, 10),
                ]);
            }
        }
    }

    private function movimiento(array $clave, string $tipo, float $monto, string $moneda, float $usd, ?int $retencion, string $fecha, Ref $agente): array
    {
        return [
            'tabla' => 'movimientoreserva', 'clave' => null, 'visita' => null,
            'fila' => [
                'idreserva' => Ref::fila('reserva', $clave), 'tipo' => $tipo, 'monto' => $monto, 'moneda' => $moneda,
                'tipocambio' => $moneda === 'BOB' ? self::TIPO_CAMBIO : null, 'montousd' => $usd, 'porcentajeretencion' => $retencion,
                'comprobante' => Ref::comprobante($tipo === 'COBRO' ? 'RV' : 'DV', 'movimientoreserva'), 'fecha' => $fecha, 'idusuario' => $agente,
            ],
        ];
    }

    /** Un pago de un contrato. $datos = [día, monto, moneda, estado]. Solo los PAGADO llevan comprobante. */
    private function pago(array $claveContrato, array $datos, string $titulo): array
    {
        [$dia, $monto, $moneda, $estado] = $datos;
        $fecha = $this->dia($dia);

        // Dos pagos de un mismo contrato nunca comparten fecha y hora exactas (así se reconocen sin ambigüedad).
        while (isset($this->fechasPago[$titulo . '|' . $fecha])) {
            $fecha = CarbonImmutable::parse($fecha)->addSecond()->format('Y-m-d H:i:s');
        }
        $this->fechasPago[$titulo . '|' . $fecha] = true;

        $usd = $moneda === 'BOB' ? round($monto / self::TIPO_CAMBIO, 2) : round($monto, 2);

        return [
            'fecha' => $fecha, 'moneda' => $moneda, 'clave' => $claveContrato + ['fechapago' => $fecha],
            'fila' => [
                'idcontrato' => Ref::fila('contrato', $claveContrato), 'monto' => $monto, 'moneda' => $moneda,
                'tipocambio' => $moneda === 'BOB' ? self::TIPO_CAMBIO : null, 'fechapago' => $fecha,
                'comprobante' => $estado === 'PAGADO' ? Ref::comprobante('CP', 'pago') : null,
                'estado' => $estado, 'montousd' => $usd, 'metodo' => 'EFECTIVO', 'idusuario' => Ref::asistente(),
            ],
        ];
    }

    /** Cambio de estado de una propiedad con su fila de historial (como lo hace CU8). */
    private function estado(int $n, string $fecha, string $anterior, string $nuevo, string $motivo): void
    {
        $this->agregar('historial', [
            'tipo' => 'CAMBIO_ESTADO', 'valoranterior' => $this->json(['estado' => $anterior]), 'valoractual' => $this->json(['estado' => $nuevo]),
            'motivo' => $motivo, 'fecha' => $fecha, 'idusuario' => Ref::agente($this->props[$n]['slot']), 'idpropiedad' => $this->propiedad($n),
        ]);
    }

    // --------------------------------------------------------------------------- intereses y seguimientos (CRM)

    private function intereses(): void
    {
        $hechos = [];
        $mensajes = ['Me interesa conocer más detalles de esta propiedad.', 'Quisiera agendar una visita lo antes posible.', '¿Aceptan financiamiento bancario?',
            'Busco algo así para mi familia, ¿sigue disponible?', 'Estoy comparando varias opciones en la zona.', null];

        $agregar = function (int $i, int $n, int $dia) use (&$hechos, $mensajes): void {
            if (isset($hechos[$i . '|' . $n])) {
                return;
            }
            $hechos[$i . '|' . $n] = true;
            $this->agregar('interes', [
                'idpropiedad' => $this->propiedad($n), 'idcliente' => $this->cliente($i), 'mensaje' => $this->azar->elegir($mensajes), 'fecha' => $this->fecha($dia),
            ]);
        };

        foreach ($this->clientes as $i => $c) {
            if ($c['prop'] !== null) {
                $agregar($i, $c['prop'], max(2, $c['registro'] - $this->azar->entero(0, 2)));
            }
        }

        for ($k = 0; $k < 14; $k++) {
            $i = $this->azar->entero(14, count($this->clientes) - 1);
            $n = $this->azar->entero(1, count($this->props));
            // Después de registrarse el cliente y de publicarse la propiedad.
            $agregar($i, $n, $this->azar->entero(2, max(2, min($this->clientes[$i]['registro'], $this->props[$n]['dias']) - 1)));
        }
    }

    private function seguimientos(): void
    {
        $notas = ['Se llamó al cliente y confirmó su interés en la propiedad.', 'Pidió más fotos y los datos del propietario.',
            'Quedó en visitar la propiedad la próxima semana.', 'Consultó por formas de pago y financiamiento.', 'No contestó la llamada; se dejó mensaje.',
            'Se envió la ubicación y el detalle por WhatsApp.', 'Reunión en la oficina para revisar la propuesta.',
            'Pidió una rebaja del precio; se consultó con el propietario.', 'Confirmó que tiene el anticipo disponible.', 'Prefiere esperar unas semanas para decidir.'];
        $medios = ['LLAMADA' => 35, 'WHATSAPP' => 30, 'CORREO' => 10, 'REUNION' => 18, 'OTRO' => 7];

        foreach ($this->clientes as $i => $c) {
            $cantidad = $i < 14 ? $this->azar->entero(3, 4) : ($i < 18 ? $this->azar->entero(2, 3) : $this->azar->entero(0, 3));
            if ($cantidad === 0) {
                continue;
            }

            // Fechas de contacto escalonadas entre el registro del cliente y ayer.
            $dias = [];
            for ($k = 0; $k < $cantidad; $k++) {
                $dias[] = $this->azar->entero(1, max(1, $c['registro'] - 1));
            }
            rsort($dias); // del más antiguo al más reciente

            foreach ($dias as $k => $dia) {
                $esUltimo = $k === $cantidad - 1;
                $contacto = $this->dia($dia);
                $proximo = $esUltimo
                    ? ($this->azar->probabilidad(55) ? $this->fecha(-$this->azar->entero(1, 20)) : null) // el último puede tener un próximo contacto futuro
                    : ($dia > 3 ? $this->fecha(max(1, $dia - $this->azar->entero(2, 10))) : null);

                $propiedad = $c['prop'] !== null && $this->azar->probabilidad(60) ? $this->propiedad($c['prop']) : null;

                $this->agregar('seguimiento', [
                    'idcliente' => $this->cliente($i), 'idusuario' => Ref::agente($c['slot']), 'idpropiedad' => $propiedad,
                    'medio' => $this->azar->ponderado($medios), 'nota' => $this->azar->elegir($notas),
                    'fechacontacto' => $contacto, 'proximocontacto' => $proximo,
                    'fecharegistro' => CarbonImmutable::parse($contacto)->addMinutes($this->azar->entero(1, 20))->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------------------- inquilinos y reseñas

    private function inquilinos(): void
    {
        $ocupaciones = ['Ingeniero civil', 'Docente universitaria', 'Comerciante', 'Médico', 'Contadora', 'Arquitecto', 'Empleada bancaria', 'Abogado'];
        // Los 4 inquilinos con contrato (posiciones 9 a 12) y 4 más (14 a 17).
        $quienes = [
            9 => ['DEPOSITO', 'Depósito de dos meses de alquiler', 'INACTIVO'], 10 => ['GARANTE', 'Garante: hermano con propiedad en Equipetrol', 'ACTIVO'],
            11 => ['DEPOSITO', 'Depósito de un mes de alquiler', 'ACTIVO'], 12 => ['OTRA', 'Boleta de garantía bancaria', 'INACTIVO'],
            14 => ['GARANTE', 'Garante: empleador con carta de respaldo', 'ACTIVO'], 15 => ['NINGUNA', null, 'ACTIVO'],
            16 => ['DEPOSITO', 'Depósito de dos meses de alquiler', 'ACTIVO'], 17 => ['OTRA', 'Póliza de caución de alquiler', 'INACTIVO'],
        ];

        foreach ($quienes as $i => [$garantia, $detalle, $estado]) {
            $c = $this->clientes[$i];
            $this->agregar('inquilino', [
                'idcliente' => $this->cliente($i), 'ocupacion' => $this->azar->elegir($ocupaciones), 'tipogarantia' => $garantia,
                'detallegarantia' => $detalle, 'estado' => $estado, 'fecharegistro' => $this->dia(max(1, $c['registro'] - $this->azar->entero(1, 5))),
            ]);
        }
    }

    private function resenas(): void
    {
        // [propiedad, cliente, puntuación, días atrás, comentario]: solo en propiedades con contrato de ese cliente.
        $lista = [
            [1, 0, 5, 55, 'Excelente atención del agente y la casa tal como la describieron. Todo el trámite fue claro.'],
            [3, 2, 4, 60, 'Buen terreno y papeles en orden. El proceso tardó un poco más de lo esperado.'],
            [13, 12, 4, 15, null],
            [2, 1, 3, 12, 'El departamento es bonito, aunque las expensas son más altas de lo que pensaba.'],
            [11, 10, 5, 9, 'Muy buena ubicación y el propietario atento. Recomiendo la propiedad.'],
            [14, 13, 4, 8, null],
        ];

        foreach ($lista as [$n, $i, $puntos, $dia, $comentario]) {
            $this->agregar('resena', [
                'idpropiedad' => $this->propiedad($n), 'idcliente' => $this->cliente($i), 'puntuacion' => $puntos,
                'comentario' => $comentario, 'fecha' => $this->dia($dia),
            ]);
        }
    }

    // ----------------------------------------------------------------------------------------------- visitas

    private function visitas(): void
    {
        // Ventanas (días atrás, de más antiguo a más reciente) en que cada propiedad se podía visitar.
        $ventanas = [
            1 => [[117, 93]], 2 => [[111, 53]], 3 => [[115, 105]], 4 => [[94, 8]], 5 => [[87, 5]], 6 => [[83, 42], [23, 3]], 7 => [[78, 32], [21, 3]],
            8 => [[99, 77], [57, 3]], 9 => [[104, 94], [75, 3]], 10 => [[113, 106], [81, 3]], 11 => [[107, 101]], 12 => [[69, 67]],
            13 => [[120, 114], [19, 3]], 14 => [[67, 58]],
        ];
        // [visitas pasadas, visitas futuras]: las futuras solo en propiedades que hoy están disponibles.
        $cantidades = [1 => [3, 0], 2 => [5, 0], 3 => [2, 0], 4 => [4, 0], 5 => [4, 0], 6 => [3, 2], 7 => [3, 1], 8 => [2, 2], 9 => [2, 1], 10 => [2, 1], 11 => [2, 0], 12 => [1, 0], 13 => [3, 0], 14 => [2, 0]];
        $motivosCancelacion = ['El cliente tuvo un imprevisto.', 'El cliente ya eligió otra propiedad.', 'Lluvia fuerte ese día.', 'El cliente no pudo asistir.'];
        $motivosReagenda = ['El cliente pidió cambiar el horario.', 'El agente tuvo otra cita ese día.', 'Se reprogramó por pedido del propietario.'];

        foreach ($cantidades as $n => [$pasadas, $futuras]) {
            $slot = $this->props[$n]['slot'];

            for ($k = 0; $k < $pasadas + $futuras; $k++) {
                $futura = $k >= $pasadas;

                for ($intento = 0; $intento < 40; $intento++) {
                    if ($futura) {
                        $dia = -$this->azar->entero(1, 25);
                    } else {
                        [$desde, $hasta] = $this->azar->elegir($ventanas[$n]);
                        $dia = $this->azar->entero($hasta, $desde);
                    }
                    $hora = $this->azar->entero(8, 17);
                    $llave = $slot . '|' . $dia . '|' . $hora;
                    $i = $this->azar->entero(0, count($this->clientes) - 1);

                    // Sin cruces de horario del mismo agente y con el cliente ya registrado.
                    if (isset($this->ocupadas[$llave]) || $this->clientes[$i]['registro'] < $dia) {
                        continue;
                    }
                    $this->ocupadas[$llave] = true;

                    $estado = $futura
                        ? $this->azar->ponderado(['PROGRAMADA' => 70, 'REAGENDADA' => 30])
                        : $this->azar->ponderado(['REALIZADA' => 60, 'CANCELADA' => 40]);

                    $fecha = $this->fecha($dia);
                    $horaTexto = sprintf('%02d:00:00', $hora);

                    $this->agregar('visita', [
                        'idpropiedad' => $this->propiedad($n), 'idcliente' => $this->cliente($i), 'fecha' => $fecha, 'hora' => $horaTexto, 'estado' => $estado,
                        'observacion' => $this->azar->probabilidad(35) ? 'El cliente pidió ver también el patio y la cochera.' : null,
                        'motivo' => $estado === 'CANCELADA' ? $this->azar->elegir($motivosCancelacion) : ($estado === 'REAGENDADA' ? $this->azar->elegir($motivosReagenda) : null),
                    ], null, ['agente' => Ref::agente($slot), 'fecha' => $fecha, 'hora' => $horaTexto]);
                    break;
                }
            }
        }
    }
}
