<?php

namespace App;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Short database transactions that survive deadlocks. Every multi-step write (disputes, proof, check-ins, cheers,
 * votes, friend requests, invites, league results) runs through here, so when MySQL picks it as a deadlock victim
 * ("SQLSTATE[40001] 1213") or a lock wait times out, the whole transaction is retried instead of showing an error.
 *
 * Rules for callers: keep the callback short, lock rows in a consistent order (the check-in's task row before its
 * disputes, a parent before its children), and never call S3, email or the AI inside it. Do those after it returns.
 */
final class Atomic
{
    use DetectsConcurrencyErrors;

    public const ATTEMPTS = 3;

    /**
     * Run the callback in a transaction, retrying on deadlocks and lock timeouts.
     *
     * Only the outermost transaction retries: inside another transaction a deadlock has already rolled back the
     * outer work, so the error is passed up for the outer level to retry.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback, int $attempts = self::ATTEMPTS): mixed
    {
        $outermost = DB::transactionLevel() === 0;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $exception) {
                if (! $outermost || $attempt >= $attempts || ! (new self)->causedByConcurrencyError($exception)) {
                    throw $exception;
                }

                // Retried, so it's not a failure: a warning and a counter, never an error email.
                self::noteContention('deadlock-retried', $exception);

                // A short, growing, jittered pause so the two sides don't collide again.
                usleep(random_int(20_000, 60_000) * $attempt);
            }
        }
    }

    /**
     * Run work that only one request should do at a time (finishing a league's season, re-checking its medals).
     * With $wait = 0 a request that finds the work already underway skips it and returns null right away: the other
     * request is doing it, and its result is what everyone reads. With $wait > 0 it waits up to that many seconds; if
     * it still can't start, that's logged as a warning (the work is idempotent and will run again), not an error.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn|null
     */
    public static function exclusively(string $key, Closure $callback, int $wait = 0, int $seconds = 60): mixed
    {
        $lock = Cache::lock('exclusive:'.$key, $seconds);

        if ($wait === 0) {
            $result = $lock->get($callback);

            if ($result === false) {
                self::noteContention('skipped-busy', null, $key);

                return null;
            }

            return $result;
        }

        try {
            return $lock->block($wait, $callback);
        } catch (LockTimeoutException $exception) {
            self::noteContention('lock-timeout', $exception, $key);

            return null;
        }
    }

    /**
     * Log a deadlock that was retried, or work skipped or postponed because it was already underway: a warning and
     * a per-day counter (cache key "contention:{kind}:{date}"), never an error email.
     */
    public static function noteContention(string $kind, ?Throwable $exception = null, ?string $key = null): void
    {
        try {
            $counter = 'contention:'.$kind.':'.now()->toDateString();
            Cache::add($counter, 0, now()->addDays(2));
            $count = Cache::increment($counter);
        } catch (Throwable) {
            $count = null;
        }

        Log::warning('Concurrency: '.$kind, array_filter(['key' => $key, 'count_today' => $count, 'error' => $exception ? str($exception->getMessage())->limit(200)->value() : null]));
    }

    /**
     * Whether an exception is a deadlock or lock timeout (for tests and callers that want to know).
     */
    public static function isConcurrencyError(Throwable $exception): bool
    {
        return (new self)->causedByConcurrencyError($exception);
    }
}
