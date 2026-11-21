<?php

namespace App\Modules\GestionPropiedades\CU19_CargarImagenes;

use App\Http\Controllers\Controller;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use App\Modules\GestionPropiedades\Compartido\GestorImagenes;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU19: Cargar imágenes de una propiedad (Administrador, o Agente en las que él registró)
 * Pantalla de galería: máximo 8 imágenes JPG, PNG o WebP de hasta 4 MB. Reutiliza GestorImagenes (como CU5 y CU6).
 *  1. Muestra la galería con la portada y el contador   -> galeria()
 *  2. Sube una o varias imágenes                         -> subir()   (validación en SubirImagenesRequest)
 *  3. Cambia la portada                                  -> portada()
 *  4. Elimina una imagen (nunca la última)               -> eliminar()
 * Cada cambio queda en el historial de la propiedad y en la bitácora.
 */
class ImagenesController extends Controller
{
    public function galeria(Propiedad $propiedad): View
    {
        self::autorizar($propiedad);

        return view('gestionpropiedades::CU19_CargarImagenes.galeria', [
            'propiedad' => $propiedad,
            'imagenes' => $propiedad->imagenes()->orderBy('idimagen')->get(),
            'maximo' => SubirImagenesRequest::MAXIMO,
        ]);
    }

    public function subir(SubirImagenesRequest $request, Propiedad $propiedad): RedirectResponse
    {
        $antes = $propiedad->imagenes()->count();
        $archivos = array_values((array) $request->file('imagenes'));

        DB::transaction(function () use ($propiedad, $archivos) {
            GestorImagenes::guardar($propiedad, $archivos);
            GestorImagenes::definirPortada($propiedad, null); // si aún no había portada, la primera queda como portada
        });

        $total = count($archivos);
        $this->registrar($propiedad, 'IMAGENES_CARGADAS', "Imágenes: se cargaron {$total}", $antes, $antes + $total,
            "Cargó {$total} imagen(es) a la propiedad {$propiedad->codigo}");

        return redirect()->route('propiedades.imagenes', $propiedad)
            ->with('status', $total === 1 ? 'Se cargó 1 imagen.' : "Se cargaron {$total} imágenes.");
    }

    public function portada(Propiedad $propiedad, Imagen $imagen): RedirectResponse
    {
        self::autorizar($propiedad);
        $this->pertenece($propiedad, $imagen);

        if (! $imagen->portada) {
            GestorImagenes::definirPortada($propiedad, 'img-' . $imagen->idimagen);

            $total = $propiedad->imagenes()->count();
            $this->registrar($propiedad, 'PORTADA_CAMBIADA', 'Imágenes: portada cambiada', $total, $total,
                "Cambió la portada de la propiedad {$propiedad->codigo}");
        }

        return redirect()->route('propiedades.imagenes', $propiedad)->with('status', 'La portada se actualizó.');
    }

    public function eliminar(Propiedad $propiedad, Imagen $imagen): RedirectResponse
    {
        self::autorizar($propiedad);
        $this->pertenece($propiedad, $imagen);

        $antes = $propiedad->imagenes()->count();

        if ($antes <= 1) {
            return redirect()->route('propiedades.imagenes', $propiedad)
                ->with('error', 'La propiedad debe conservar al menos una imagen.');
        }

        DB::transaction(function () use ($propiedad, $imagen) {
            GestorImagenes::eliminar($imagen);
            GestorImagenes::definirPortada($propiedad, null); // si era la portada, la primera restante pasa a serlo
        });

        $this->registrar($propiedad, 'IMAGEN_ELIMINADA', 'Imágenes: se eliminó una', $antes, $antes - 1,
            "Eliminó una imagen de la propiedad {$propiedad->codigo}");

        return redirect()->route('propiedades.imagenes', $propiedad)->with('status', 'La imagen se eliminó.');
    }

    /** Propiedad dada de baja -> 404. Un Agente que no la registró -> 403. */
    public static function autorizar(Propiedad $propiedad): void
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol('Administrador') || $propiedad->idusuario === $usuario->idusuario,
            403,
            'Solo puedes administrar las imágenes de las propiedades que tú registraste.'
        );
    }

    /** La imagen de la URL debe ser de la propiedad de la URL. */
    private function pertenece(Propiedad $propiedad, Imagen $imagen): void
    {
        abort_unless($imagen->idpropiedad === $propiedad->idpropiedad, 404);
    }

    private function registrar(Propiedad $propiedad, string $accion, string $motivo, int $antes, int $despues, string $descripcion): void
    {
        HistorialService::registrar(Historial::MODIFICACION, $propiedad->idpropiedad,
            ['imagenes' => $antes], ['imagenes' => $despues], $motivo);

        BitacoraService::registrar($accion, 'Propiedades', $descripcion, ['idpropiedad' => $propiedad->idpropiedad]);
    }
}