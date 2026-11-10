<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda imágenes optimizadas: redimensiona a un ancho máximo, convierte a WebP
 * y elimina los metadatos (EXIF, que puede incluir la ubicación GPS de la foto).
 * Los GIF se guardan tal cual para no perder la animación.
 */
class Images
{
    public static function store(UploadedFile $file, int $maxWidth = 1600): string
    {
        $dir = date('Y/m');

        if ($file->getMimeType() === 'image/gif' || ! function_exists('imagewebp')) {
            return $file->store($dir, 'uploads');
        }

        $img = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $img) {
            return $file->store($dir, 'uploads');
        }

        $img = self::applyExifOrientation($img, $file);

        if (imagesx($img) > $maxWidth) {
            $scaled = imagescale($img, $maxWidth, -1, IMG_BICUBIC);
            imagedestroy($img);
            $img = $scaled;
        }

        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        ob_start();
        imagewebp($img, null, 82);
        $data = ob_get_clean();
        imagedestroy($img);

        $path = $dir.'/'.Str::random(40).'.webp';
        Storage::disk('uploads')->put($path, $data);

        return $path;
    }

    /** Las fotos del móvil vienen "giradas" con una etiqueta EXIF: la aplicamos antes de borrarla. */
    private static function applyExifOrientation(\GdImage $img, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $img;
        }
        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $rotated = match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };

        return $rotated ?: $img;
    }
}
