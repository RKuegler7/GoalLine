<?php

namespace App\Services;

use App\Atomic;
use App\Notifications\AdminAlert;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Emails the admins when something breaks, at most once every 15 minutes for the same error, so a bad day isn't a flood.
 *
 * "The same error" means the same kind of exception with the same message once the parts that change on every
 * request are taken out (file paths, ids, numbers, quoted values, request ids). A missing file at avatars/a.jpg and
 * one at avatars/b.jpg are one error, whichever line threw them.
 */
class ErrorAlerts
{
    public const QUIET_MINUTES = 15;

    public function report(Throwable $exception): void
    {
        // Waiting on a lock for idempotent work isn't a failure (the work runs again): a warning and a counter only.
        // Deadlocks that were retried never get here; App\Atomic logs those the same way.
        if ($exception instanceof LockTimeoutException) {
            Atomic::noteContention('lock-timeout', $exception);

            return;
        }

        $admins = config('goalline.admin_emails');

        if ($admins === []) {
            return;
        }

        try {
            // If the cache can't record it, send nothing rather than risk a flood.
            if (! Cache::add('error-alert:'.self::fingerprint($exception), true, now()->addMinutes(self::QUIET_MINUTES))) {
                return;
            }

            Notification::route('mail', $admins)->notifyNow(new AdminAlert(
                'Error: '.class_basename($exception).' in '.basename($exception->getFile()).':'.$exception->getLine(),
                str($exception->getMessage())->limit(500).' (Full details are in the log.)',
            ));
        } catch (Throwable) {
            // Never let an alert failure hide the original error.
        }
    }

    /**
     * What makes two errors "the same": the exception class and its message with the changing details removed.
     */
    public static function fingerprint(Throwable $exception): string
    {
        $message = preg_replace([
            '/"[^"]*"|\'[^\']*\'|`[^`]*`/',                       // Quoted values.
            '/\b[\w.-]*\/[\w.\/-]+/',                             // Paths and keys, like avatars/abc123.jpg.
            '/\b[0-9a-f]{8,}\b/i',                                // Hashes and request ids.
            '/\d+/',                                              // Ids, sizes, times.
            '/\s+/',
        ], ['?', '?', '?', '#', ' '], $exception->getMessage());

        return sha1($exception::class.'|'.trim(mb_substr((string) $message, 0, 300)));
    }
}
