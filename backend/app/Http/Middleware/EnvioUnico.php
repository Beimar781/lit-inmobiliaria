<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita que un mismo formulario se procese dos veces (doble clic, reenvío con F5 o "atrás").
 * El formulario lleva un campo oculto `_envio` con un código único; la primera vez que llega se
 * marca como usado y las siguientes veces se rechaza. Si el campo no viene, la petición pasa igual.
 */
class EnvioUnico
{
    private const CLAVE = 'envios_usados';
    private const MAXIMO = 50;

    /** Campo oculto con un código nuevo para poner dentro de un formulario. */
    public static function campo(): string
    {
        return '<input type="hidden" name="_envio" value="' . Str::random(32) . '">';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $codigo = $request->input('_envio');

        if (is_string($codigo) && $codigo !== '') {
            $usados = (array) $request->session()->get(self::CLAVE, []);

            if (in_array($codigo, $usados, true)) {
                return redirect()->back()->with('aviso', 'Este formulario ya fue enviado. Revisa si el registro ya se creó antes de repetirlo.');
            }

            $usados[] = $codigo;
            $request->session()->put(self::CLAVE, array_slice($usados, -self::MAXIMO));
        }

        return $next($request);
    }
}
