<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;

/**
 * Imagen de prueba: un archivo real y cerrado (no un recurso temporal abierto como UploadedFile::fake()),
 * para que Windows pueda borrar la carpeta temporal al terminar cada prueba.
 * Informa el tamaño que se le pida, así se puede probar el límite de 4 MB sin crear archivos enormes.
 */
class ImagenDePrueba extends UploadedFile
{
    public int $kbInformados = 100;

    public function getSize(): int|false
    {
        return $this->kbInformados * 1024;
    }
}
