<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves an uploaded photo (avatar, cover, post photo, proof, goal photo) with a single read and no metadata calls:
 * the content type comes from the database, saved at upload time, or from the file's extension for older rows.
 *
 * A photo whose file has gone missing never breaks the page or emails an error. The viewer gets a placeholder
 * (the person's initials for avatars), and when storage confirms the file is gone, the caller's $forget clears the
 * stale reference so later pages show the fallback without asking again.
 */
class StoredPhoto
{
    private const TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'heic' => 'image/heic',
    ];

    /**
     * Stream a stored photo, or a placeholder if it's gone.
     *
     * @param  array<string, string>  $headers
     * @param  (callable(): void)|null  $forget  Clears the stale database reference, for photos where that's safe.
     */
    public function serve(string $disk, string $path, ?string $mimeType, array $headers = [], ?string $initials = null, ?callable $forget = null): Response
    {
        $storage = Storage::disk($disk);
        $stream = $this->open($storage, $path);

        if ($stream === null) {
            $this->missing($storage, $disk, $path, $forget);

            return response($initials !== null ? self::initialsSvg($initials) : self::placeholderSvg(), 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType ?: self::typeFor($path),
            'X-Content-Type-Options' => 'nosniff',
            ...$headers,
        ]);
    }

    /**
     * A stored photo's bytes, or null if it's gone (clearing the reference through $forget when that's confirmed).
     *
     * @param  (callable(): void)|null  $forget
     */
    public function contents(string $disk, string $path, ?callable $forget = null): ?string
    {
        $storage = Storage::disk($disk);
        $stream = $this->open($storage, $path);

        if ($stream === null) {
            $this->missing($storage, $disk, $path, $forget);

            return null;
        }

        $bytes = stream_get_contents($stream);
        fclose($stream);

        return $bytes === false ? null : $bytes;
    }

    /**
     * The content type for a stored file from its extension: no storage call.
     */
    public static function typeFor(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /**
     * A round avatar with the person's initials, in the same Club Green as the initials badge on the page.
     */
    public static function initialsSvg(string $initials): string
    {
        $text = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES);

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><circle cx="48" cy="48" r="48" fill="#176b45"/><text x="48" y="48" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-size="36" font-weight="600" fill="#fafaf5">{$text}</text></svg>
            SVG;
    }

    /**
     * A calm stand-in for a photo that isn't available any more.
     */
    public static function placeholderSvg(): string
    {
        return <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#e5f3e8"/><path d="M150 195l40-50 30 36 20-24 40 38z" fill="#176b45" opacity=".35"/><circle cx="250" cy="115" r="16" fill="#176b45" opacity=".35"/><text x="200" y="250" text-anchor="middle" font-family="system-ui, sans-serif" font-size="18" fill="#1e2922">Photo unavailable</text></svg>
            SVG;
    }

    /**
     * One read of the file: GET, never HEAD. Null when it can't be read.
     *
     * @return resource|null
     */
    private function open(Filesystem $storage, string $path): mixed
    {
        try {
            $stream = $storage->readStream($path);
        } catch (FilesystemException) {
            return null;
        }

        return is_resource($stream) ? $stream : null;
    }

    /**
     * Log the missing file quietly, and clear the reference only when storage confirms it's gone, so a storage
     * outage never wipes good references.
     *
     * @param  (callable(): void)|null  $forget
     */
    private function missing(Filesystem $storage, string $disk, string $path, ?callable $forget): void
    {
        try {
            $gone = $storage->missing($path);
        } catch (Throwable) {
            $gone = false;
        }

        Log::warning('Uploaded file could not be read', ['disk' => $disk, 'path' => $path, 'confirmed_missing' => $gone]);

        if ($gone && $forget !== null) {
            $forget();
        }
    }
}
