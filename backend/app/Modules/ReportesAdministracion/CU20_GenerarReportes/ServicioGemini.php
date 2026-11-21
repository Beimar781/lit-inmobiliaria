<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Implementación con la API de Google Gemini (cliente HTTP de Laravel). La llave solo viene del .env y nunca se registra. */
class ServicioGemini implements ServicioDeIa
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function disponible(): bool
    {
        return filled(config('services.ia.key'));
    }

    public function interpretar(string $solicitud, array $tiposPermitidos): ?array
    {
        $texto = $this->generar(InterpretadorDeSolicitud::instruccion($tiposPermitidos), $solicitud, true);
        $datos = $texto === null ? null : json_decode($texto, true);

        return is_array($datos) ? $datos : null;
    }

    public function resumir(array $datosAgregados): ?string
    {
        $instruccion = 'Eres un analista de una inmobiliaria. Redacta en español, en un solo párrafo de máximo 3 frases, un resumen '
            . 'claro de las cifras de un reporte. Usa ÚNICAMENTE las cifras recibidas; no inventes datos ni nombres.';

        return $this->generar($instruccion, json_encode($datosAgregados, JSON_UNESCAPED_UNICODE), false);
    }

    private function generar(string $instruccion, string $contenido, bool $json): ?string
    {
        if (! $this->disponible()) {
            return null;
        }

        try {
            $respuesta = Http::timeout((int) config('services.ia.timeout', 15))
                ->withHeaders(['x-goog-api-key' => config('services.ia.key')]) // en la cabecera, no en la URL
                ->acceptJson()
                ->post(self::URL . config('services.ia.modelo') . ':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $instruccion]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $contenido]]]],
                    'generationConfig' => array_filter([
                        'temperature' => 0,
                        'responseMimeType' => $json ? 'application/json' : null,
                    ], fn ($v) => $v !== null),
                ]);
        } catch (\Throwable $e) {
            Log::warning('IA: el servicio no respondió (' . class_basename($e) . ').');

            return null;
        }

        if ($respuesta->failed()) {
            Log::warning('IA: error HTTP ' . $respuesta->status() . '.');

            return null;
        }

        $texto = data_get($respuesta->json(), 'candidates.0.content.parts.0.text');

        return is_string($texto) && trim($texto) !== '' ? trim($texto) : null;
    }
}