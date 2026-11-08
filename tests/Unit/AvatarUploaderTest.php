<?php

namespace App\Tests\Unit;

use App\Service\AvatarUploader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AvatarUploaderTest extends KernelTestCase
{
    /** Crée un JPEG 800×400 contenant un bloc EXIF (rotation + position GPS). */
    public static function jpegWithGpsExif(string $path): void
    {
        $image = imagecreatetruecolor(800, 400);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $gpsOffset = 8 + 2 + 2 * 12 + 4;
        $tiff = "II*\x00" . pack('V', 8)
            . pack('v', 2) . pack('vvVV', 0x0112, 3, 1, 6) . pack('vvVV', 0x8825, 4, 1, $gpsOffset) . pack('V', 0)
            . pack('v', 1) . pack('vvVa4', 0x0001, 2, 2, "N\x00\x00\x00") . pack('V', 0);
        $exif = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));
    }

    public function testUploadStripsExifAndResizes(): void
    {
        $source = sys_get_temp_dir() . '/avatar-gps.jpg';
        self::jpegWithGpsExif($source);
        self::assertSame('N', @exif_read_data($source)['GPSLatitudeRef'] ?? null, 'le fichier de test doit contenir une position GPS');

        $uploader = static::getContainer()->get(AvatarUploader::class);
        $filename = $uploader->upload(new UploadedFile($source, 'photo.jpg', 'image/jpeg', null, true));

        $stored = static::getContainer()->getParameter('app.avatar_dir') . '/' . $filename;
        [$width, $height, , , ] = getimagesize($stored) ?: [0, 0];
        self::assertStringEndsWith('.webp', $filename);
        self::assertSame([512, 512], [$width, $height]);
        self::assertStringNotContainsString('Exif', (string) file_get_contents($stored));

        unlink($stored);
    }

    public function testUnreadableFileIsRejected(): void
    {
        $source = sys_get_temp_dir() . '/avatar-invalide.jpg';
        file_put_contents($source, 'pas une image');

        $this->expectException(\RuntimeException::class);
        static::getContainer()->get(AvatarUploader::class)->upload(new UploadedFile($source, 'x.jpg', 'image/jpeg', null, true));
    }
}
