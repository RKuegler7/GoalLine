<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Cleans a "Show your work" photo before it's saved: re-encoded with GD where the server has it (dropping all
 * metadata), otherwise with the metadata blocks removed directly. Either way EXIF, GPS location, and other
 * embedded data never reach storage.
 */
class ProofImage
{
    /**
     * @return array{bytes: string, extension: string}
     */
    public function clean(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $type = $this->detectType($bytes);

        if ($type === null) {
            throw new RuntimeException('Not a JPG, PNG, or WebP image.');
        }

        if (function_exists('imagecreatefromstring') && ($image = @imagecreatefromstring($bytes)) !== false) {
            return ['bytes' => $this->reencode($image, $type), 'extension' => $type];
        }

        return ['bytes' => match ($type) {
            'jpg' => $this->stripJpeg($bytes),
            'png' => $this->stripPng($bytes),
            'webp' => $this->stripWebp($bytes),
        }, 'extension' => $type];
    }

    /**
     * The real image type from the file's first bytes, whatever its name says.
     */
    public function detectType(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }

    /**
     * @param  \GdImage  $image
     */
    private function reencode($image, string $type): string
    {
        ob_start();

        match (true) {
            $type === 'png' => imagepng($image),
            $type === 'webp' && function_exists('imagewebp') => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 88),
        };

        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Drop JPEG application blocks (APP1 holds EXIF and GPS; APP2–APP15 hold other metadata) and comments.
     * APP0 (JFIF) stays, since it only describes the image format.
     */
    public function stripJpeg(string $bytes): string
    {
        $out = "\xFF\xD8";
        $position = 2;
        $length = strlen($bytes);

        while ($position + 4 <= $length && $bytes[$position] === "\xFF") {
            $marker = ord($bytes[$position + 1]);

            // Start of scan: the image data follows, copy the rest as-is.
            if ($marker === 0xDA) {
                return $out.substr($bytes, $position);
            }

            $segmentLength = unpack('n', substr($bytes, $position + 2, 2))[1];
            $segment = substr($bytes, $position, 2 + $segmentLength);
            $isMetadata = ($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE;

            if (! $isMetadata) {
                $out .= $segment;
            }

            $position += 2 + $segmentLength;
        }

        return $out.substr($bytes, $position);
    }

    /**
     * Drop PNG text, EXIF, and timestamp chunks; keep everything needed to draw the image.
     */
    public function stripPng(string $bytes): string
    {
        $out = substr($bytes, 0, 8);
        $position = 8;
        $drop = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

        while ($position + 12 <= strlen($bytes)) {
            $chunkLength = unpack('N', substr($bytes, $position, 4))[1];
            $type = substr($bytes, $position + 4, 4);
            $chunk = substr($bytes, $position, 12 + $chunkLength);

            if (! in_array($type, $drop, true)) {
                $out .= $chunk;
            }

            $position += 12 + $chunkLength;
        }

        return $out;
    }

    /**
     * Drop WebP EXIF and XMP chunks, then fix the file's size header.
     */
    public function stripWebp(string $bytes): string
    {
        $body = 'WEBP';
        $position = 12;

        while ($position + 8 <= strlen($bytes)) {
            $type = substr($bytes, $position, 4);
            $chunkLength = unpack('V', substr($bytes, $position + 4, 4))[1];
            $padded = $chunkLength + ($chunkLength % 2);

            if (! in_array($type, ['EXIF', 'XMP '], true)) {
                $body .= substr($bytes, $position, 8 + $padded);
            }

            $position += 8 + $padded;
        }

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
