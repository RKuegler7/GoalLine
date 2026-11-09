<?php

namespace App;

use Closure;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\DB;
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

                // A short, growing, jittered pause so the two sides don't collide again.
                usleep(random_int(20_000, 60_000) * $attempt);
            }
        }
    }

    /**
     * Whether an exception is a deadlock or lock timeout (for tests and callers that want to know).
     */
    public static function isConcurrencyError(Throwable $exception): bool
    {
        return (new self)->causedByConcurrencyError($exception);
    }
}
