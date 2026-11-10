<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Is GoalLine able to serve people right now? Checks the app, the database, photo storage, the cache and the
 * background queue, and says only "ok" or "failing" for each: never a host, key, bucket or error message.
 */
class HealthCheck
{
    /**
     * Storage is checked by writing a tiny file; at most once a minute, so a busy monitor can't run up S3 requests.
     */
    public const STORAGE_EVERY_SECONDS = 60;

    /**
     * @return array{status: 'ok'|'failing', checks: array{app: string, database: string, storage: string, cache: string, queue: string}}
     */
    public function run(): array
    {
        $checks = [
            'app' => 'ok',
            'database' => $this->attempt(fn () => $this->pingDatabase()),
            'cache' => $this->attempt(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $read = Cache::get($key);
                Cache::forget($key);

                return $read === 'ok';
            }),
        ];
        $checks['storage'] = $this->storage($checks['cache'] === 'ok');
        $checks['queue'] = $checks['database'] === 'ok' ? $this->attempt(fn () => app(AdminStats::class)->queue()['healthy']) : 'failing';

        return [
            'status' => in_array('failing', $checks, true) ? 'failing' : 'ok',
            'checks' => $checks,
        ];
    }

    /**
     * One round trip to the database.
     */
    protected function pingDatabase(): bool
    {
        return DB::select('select 1 as ok') !== [];
    }

    private function storage(bool $canRemember): string
    {
        if ($canRemember && Cache::get('health:storage') === 'ok') {
            return 'ok';
        }

        $result = $this->attempt(function () {
            $disk = Storage::disk('uploads');
            $disk->put('health/ping.txt', 'ok');
            $working = $disk->get('health/ping.txt') === 'ok';
            $disk->delete('health/ping.txt');

            return $working;
        });

        if ($canRemember && $result === 'ok') {
            Cache::put('health:storage', 'ok', self::STORAGE_EVERY_SECONDS);
        }

        return $result;
    }

    /**
     * Run one check: "ok" if it works (and doesn't return false), "failing" otherwise.
     */
    private function attempt(callable $check): string
    {
        try {
            return $check() === false ? 'failing' : 'ok';
        } catch (Throwable) {
            return 'failing';
        }
    }
}
