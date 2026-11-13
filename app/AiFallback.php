<?php

namespace App;

use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Makes falling back to built-in plans and questions visible. The member never sees an error (the templates take over),
 * but every fallback is logged as a warning with a per-day counter, and a rejected key says so plainly, so a bad
 * ANTHROPIC_API_KEY can't hide. `php artisan goalline:ai-check` tests the key directly.
 */
final class AiFallback
{
    /**
     * What fell back: intake questions, a roadmap, next-goal ideas.
     */
    public const KINDS = ['questions', 'plan', 'ideas'];

    /**
     * Log one fallback. Returns today's count for that kind.
     *
     * @param  array<string, mixed>  $context
     */
    public static function note(string $kind, ?Throwable $exception = null, array $context = []): int
    {
        $today = now()->toDateString();
        $count = 0;

        try {
            Cache::add("ai-fallback:{$kind}:{$today}", 0, now()->addDays(2));
            $count = (int) Cache::increment("ai-fallback:{$kind}:{$today}");
        } catch (Throwable) {
            // The counter is a nicety; the log line below still goes out.
        }

        $keyProblem = $exception instanceof AuthenticationException || $exception instanceof PermissionDeniedException;

        Log::warning("AI fallback: built-in {$kind} used instead of the coach.", array_filter([
            ...$context,
            'count_today' => $count,
            'reason' => match (true) {
                blank(config('services.anthropic.key')) => 'No ANTHROPIC_API_KEY is set.',
                $keyProblem => 'The API key was rejected. Run php artisan goalline:ai-check.',
                $exception !== null => self::safe($exception->getMessage()),
                default => null,
            },
        ], fn ($value) => $value !== null && $value !== ''));

        return $count;
    }

    /**
     * Today's fallback counts, for the admin page.
     *
     * @return array<string, int>
     */
    public static function today(): array
    {
        $today = now()->toDateString();

        return collect(self::KINDS)->mapWithKeys(fn (string $kind) => [$kind => (int) Cache::get("ai-fallback:{$kind}:{$today}", 0)])->all();
    }

    /**
     * An error message with anything that looks like an API key removed, and a sensible length.
     */
    public static function safe(string $message): string
    {
        $message = preg_replace('/sk-ant-[A-Za-z0-9_\-]+/', 'sk-ant-…', $message) ?? '';

        if (filled($key = (string) config('services.anthropic.key'))) {
            $message = str_replace($key, '[the key]', $message);
        }

        return mb_strimwidth(trim(preg_replace('/\s+/', ' ', $message) ?? ''), 0, 300, '…');
    }
}
