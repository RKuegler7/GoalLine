<?php

namespace App;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Which commit the running app was built from, so anyone can confirm the live site matches the final commit.
 * `php artisan goalline:deploy-zip` puts build.json (short commit hash and build time, nothing secret) at the root of
 * deploy.zip; About and /health show it. A copy run straight from git has no stamp.
 */
class BuildStamp
{
    public const FILE = 'build.json';

    /** @var array{commit: string, built_at: string}|false|null */
    private static array|false|null $cached = null;

    /**
     * @return array{commit: string, built_at: string}|null
     */
    public static function current(): ?array
    {
        if (self::$cached === null) {
            self::$cached = self::read(base_path(self::FILE)) ?? false;
        }

        return self::$cached ?: null;
    }

    /**
     * @return array{commit: string, built_at: string}|null
     */
    public static function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $commit = is_array($data) ? (string) ($data['commit'] ?? '') : '';
        $builtAt = is_array($data) ? (string) ($data['built_at'] ?? '') : '';

        if (! preg_match('/^[0-9a-f]{7,40}$/', $commit) || strtotime($builtAt) === false) {
            return null;
        }

        return ['commit' => $commit, 'built_at' => CarbonImmutable::parse($builtAt)->utc()->toIso8601String()];
    }

    /**
     * For tests: forget the stamp read so far.
     */
    public static function flush(): void
    {
        self::$cached = null;
    }
}
