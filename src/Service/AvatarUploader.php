<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Réencode les photos de profil : recadrage carré 512×512 en WebP.
 * Le réencodage supprime toutes les métadonnées EXIF (dont la position GPS)
 * et neutralise un éventuel contenu caché dans le fichier d'origine.
 */
class AvatarUploader
{
    private const SIZE = 512;

    public function __construct(
        #[Autowire('%app.avatar_dir%')] private string $avatarDir,
    ) {
    }

    /**
     * @return string Le nom du fichier enregistré
     *
     * @throws \RuntimeException si l'image ne peut pas être lue
     */
    public function upload(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getPathname()));
        if ($source === false) {
            throw new \RuntimeException('Image illisible.');
        }

        $source = $this->applyExifOrientation($source, $file);

        // Recadrage carré centré puis redimensionnement
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled(
            $target, $source,
            0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side
        );

        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0775, true);
        }

        $filename = bin2hex(random_bytes(12)) . '.webp';
        imagewebp($target, $this->avatarDir . '/' . $filename, 85);

        imagedestroy($source);
        imagedestroy($target);

        return $filename;
    }

    /**
     * Les photos de téléphone sont souvent stockées « couchées » avec une indication de rotation dans l'EXIF :
     * on applique cette rotation avant de supprimer les métadonnées.
     */
    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getPathname());
        $rotation = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $rotation !== 0 ? (imagerotate($image, $rotation, 0) ?: $image) : $image;
    }
}
