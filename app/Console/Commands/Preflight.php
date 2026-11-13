<?php

namespace App\Console\Commands;

use App\Services\AiCheck;
use App\Services\HealthCheck;
use App\Services\ResultCards\ResultCardImage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Before and after a deploy: is everything production needs set? Prints PASS, WARN or FAIL per item, never a secret
 * value, and exits non-zero if anything fails.
 */
class Preflight extends Command
{
    protected $signature = 'goalline:preflight {--skip-ai : Skip the one tiny Claude API call}';

    protected $description = 'Check the production settings (keys, https address, database, mail, S3, Claude) without printing secrets';

    /** @var list<array{0: 'PASS'|'WARN'|'FAIL', 1: string}> */
    private array $results = [];

    public function handle(HealthCheck $health, AiCheck $ai): int
    {
        $production = app()->isProduction();

        $this->check(filled(config('app.key')), 'APP_KEY is set');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL is the https address');
        $this->check(! config('app.debug') || ! $production, 'APP_DEBUG is false in production (no stack traces shown)');
        $this->check($production, 'APP_ENV is production', warnOnly: true);

        $this->check(config('database.default') !== 'sqlite' || ! $production, 'Database is MySQL, not a file on the server');
        foreach (['host', 'database', 'username', 'password'] as $key) {
            $this->check(config('database.default') === 'sqlite' || filled(config('database.connections.'.config('database.default').'.'.$key)), 'DB_'.strtoupper($key).' is set');
        }

        $this->check(config('session.driver') === 'database', 'Sessions are kept in the database (SESSION_DRIVER=database)');
        $this->check(config('cache.default') === 'database', 'Cache is kept in the database (CACHE_STORE=database)');
        $this->check(config('queue.default') === 'database', 'Background jobs use the database queue (QUEUE_CONNECTION=database)');
        $this->check((bool) config('session.secure') || ! $production, 'Session cookies are https only (SESSION_SECURE_COOKIE=true)');

        $mailer = (string) config('mail.default');
        $this->check(! in_array($mailer, ['log', 'array'], true) || ! $production, 'Mail is really sent (MAIL_MAILER is smtp or ses, not log)');
        if ($mailer === 'smtp') {
            foreach (['host', 'username', 'password'] as $key) {
                $this->check(filled(config('mail.mailers.smtp.'.$key)), 'MAIL_'.strtoupper($key).' is set');
            }
        }
        $this->check(filled(config('mail.from.address')), 'MAIL_FROM_ADDRESS is set');

        $s3 = config('filesystems.disks.uploads.driver') === 's3';
        $this->check($s3 || ! $production, 'Photos are stored on S3 (UPLOADS_DISK=s3)');
        if ($s3) {
            $this->check(filled(config('filesystems.disks.uploads.bucket')), 'AWS_BUCKET is set');
            $this->check(filled(config('filesystems.disks.uploads.region')), 'AWS_DEFAULT_REGION is set');
        }

        $this->check(filled(config('services.anthropic.key')), 'ANTHROPIC_API_KEY is set (the AI coach)');

        // One tiny real call (the same as goalline:ai-check), so a mistyped or revoked key shows up here.
        if (filled(config('services.anthropic.key')) && ! $this->option('skip-ai')) {
            $ai = $ai->run();
            $this->check($ai['ok'], 'The AI coach answers: '.$ai['message']);
        }
        $this->check(filled(config('goalline.admin_emails')), 'ADMIN_EMAILS is set (error and spend alerts)', warnOnly: true);
        $this->check(ResultCardImage::canMakePng(), 'GD can draw PNG share cards (otherwise the browser draws them)', warnOnly: true);

        try {
            $status = $health->run();

            foreach ($status['checks'] as $name => $state) {
                $this->check($state === 'ok', ucfirst($name).' is reachable and working', warnOnly: $name === 'queue');
            }
        } catch (Throwable) {
            $this->check(false, 'Health checks could run');
        }

        $switches = collect(config('goalline.messaging'))->filter()->keys()->map(fn (string $key) => 'GOALLINE_'.strtoupper($key))->implode(', ');
        $this->line('INFO  Outbound messages on: '.($switches ?: 'none (all paused)'));

        foreach ($this->results as [$status, $label]) {
            $this->line(match ($status) {
                'PASS' => "<info>PASS</info>  {$label}",
                'WARN' => "<comment>WARN</comment>  {$label}",
                'FAIL' => "<error>FAIL</error>  {$label}",
            });
        }

        $failed = collect($this->results)->where(0, 'FAIL')->count();
        $this->newLine();
        $this->line($failed === 0 ? '<info>Preflight passed.</info>' : "<error>Preflight failed: {$failed} to fix.</error>");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(bool $passed, string $label, bool $warnOnly = false): void
    {
        $this->results[] = [$passed ? 'PASS' : ($warnOnly ? 'WARN' : 'FAIL'), $label];
    }
}
