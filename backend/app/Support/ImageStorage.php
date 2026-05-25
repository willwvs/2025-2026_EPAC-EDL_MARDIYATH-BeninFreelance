<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorage
{
    public const MAX_BYTES = 5 * 1024 * 1024;

  /** @var array<string, string> */
    public const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{path: string, public_url: string}
     */
    public static function storeImage(UploadedFile $file, string $directoryUnderPublic): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Fichier trop volumineux (max. 5 Mo).');
        }

        $mime = self::detectMime($file);
        if (! isset(self::MIME_TO_EXT[$mime])) {
            throw new RuntimeException('Format non accepté (JPG, PNG ou WEBP uniquement).');
        }

        $ext = self::MIME_TO_EXT[$mime];
        $relativeDir = trim($directoryUnderPublic, '/');
        $absDir = public_path($relativeDir);
        if (! is_dir($absDir) && ! mkdir($absDir, 0755, true) && ! is_dir($absDir)) {
            throw new RuntimeException('Impossible de créer le dossier de stockage.');
        }

        $filename = (string) Str::uuid().'.'.$ext;
        $relativePath = $relativeDir.'/'.$filename;
        $absPath = public_path($relativePath);

        $file->move($absDir, $filename);
        self::compressInPlace($absPath, $mime);

        if (filesize($absPath) > self::MAX_BYTES) {
            @unlink($absPath);
            throw new RuntimeException('Image trop lourde après traitement (max. 5 Mo).');
        }

        return [
            'path' => $relativePath,
            'public_url' => '/'.ltrim($relativePath, '/'),
        ];
    }

    public static function deletePublicPath(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $full = public_path(ltrim($relativePath, '/'));
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        return '/'.ltrim($relativePath, '/');
    }

    private static function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Fichier illisible.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (! is_string($mime) || $mime === '') {
            throw new RuntimeException('Type de fichier non reconnu.');
        }

        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        return $mime;
    }

    private static function compressInPlace(string $absPath, string $mime): void
    {
        if (! extension_loaded('gd')) {
            return;
        }

        $info = @getimagesize($absPath);
        if ($info === false) {
            throw new RuntimeException('Image invalide ou corrompue.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $maxSide = 1600;

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absPath),
            'image/png' => @imagecreatefrompng($absPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absPath) : false,
            default => false,
        };

        if ($src === false) {
            throw new RuntimeException('Impossible de lire l’image.');
        }

        $newW = $width;
        $newH = $height;
        if ($width > $maxSide || $height > $maxSide) {
            if ($width >= $height) {
                $newW = $maxSide;
                $newH = (int) max(1, round($height * ($maxSide / $width)));
            } else {
                $newH = $maxSide;
                $newW = (int) max(1, round($width * ($maxSide / $height)));
            }
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            throw new RuntimeException('Traitement image impossible.');
        }

        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($dst, $absPath, 82),
            'image/png' => imagepng($dst, $absPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($dst, $absPath, 80) : false,
            default => false,
        };

        imagedestroy($dst);

        if (! $ok) {
            throw new RuntimeException('Enregistrement de l’image impossible.');
        }
    }
}
