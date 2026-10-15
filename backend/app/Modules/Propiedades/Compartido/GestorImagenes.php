<?php

namespace App\Modules\Propiedades\Compartido;

use App\Models\Imagen;
use App\Models\Propiedad;
use Illuminate\Support\Str;

/** Guarda y borra las imágenes de una propiedad (carpeta public/uploads/propiedades/<id>). */
class GestorImagenes
{
    /** Guarda las imágenes subidas y devuelve los registros creados (en el mismo orden). */
    public static function guardar(Propiedad $propiedad, array $archivos): array
    {
        $carpeta = 'uploads/propiedades/' . $propiedad->idpropiedad;
        $creadas = [];

        foreach ($archivos as $archivo) {
            // Se leen antes de mover el archivo.
            $nombreOriginal = Str::limit($archivo->getClientOriginalName(), 250, '');
            $nombreFinal = Str::uuid() . '.' . $archivo->guessExtension();

            $archivo->move(public_path($carpeta), $nombreFinal);

            $creadas[] = Imagen::create([
                'idpropiedad' => $propiedad->idpropiedad,
                'nombre' => $nombreOriginal,
                'ruta' => $carpeta . '/' . $nombreFinal,
            ]);
        }

        return $creadas;
    }

    /**
     * Deja exactamente UNA imagen de portada por propiedad.
     * $seleccion viene del formulario: "img-<id>" (imagen ya guardada) o "nueva-<n>" (n-ésima imagen recién subida).
     * Si no llega una selección válida, se conserva la portada actual o se usa la primera imagen.
     */
    public static function definirPortada(Propiedad $propiedad, ?string $seleccion, array $nuevas = []): void
    {
        $imagenes = Imagen::where('idpropiedad', $propiedad->idpropiedad)->orderBy('idimagen')->get();

        if ($imagenes->isEmpty()) {
            return;
        }

        $elegida = null;

        if (preg_match('/^img-(\d+)$/', (string) $seleccion, $m)) {
            $elegida = $imagenes->firstWhere('idimagen', (int) $m[1]);
        } elseif (preg_match('/^nueva-(\d+)$/', (string) $seleccion, $m)) {
            $nueva = $nuevas[(int) $m[1]] ?? null;
            $elegida = $nueva ? $imagenes->firstWhere('idimagen', $nueva->idimagen) : null;
        }

        $elegida ??= $imagenes->firstWhere('portada', true) ?? $imagenes->first();

        Imagen::where('idpropiedad', $propiedad->idpropiedad)
            ->where('idimagen', '!=', $elegida->idimagen)
            ->update(['portada' => false]);

        $elegida->update(['portada' => true]);
    }

    public static function eliminar(Imagen $imagen): void
    {
        $archivo = public_path($imagen->ruta);

        if (is_file($archivo)) {
            @unlink($archivo);
        }

        $imagen->delete();
    }
}
