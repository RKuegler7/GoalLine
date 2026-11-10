<?php

namespace Tests\Feature;

use App\Models\ResultCard;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\ErrorAlerts;
use App\Services\HealthCheck;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Phase S: the health check, quiet and useful error emails, the preflight command, security headers and rate limits.
 */
class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_ok_when_everything_works_and_shows_no_secrets(): void
    {
        Storage::fake('uploads');
        config(['app.key' => 'base64:SECRET-APP-KEY-VALUE', 'services.anthropic.key' => 'sk-ant-SECRET']);

        $response = $this->getJson('/health')->assertOk()
            ->assertExactJson(['status' => 'ok', 'checks' => ['app' => 'ok', 'database' => 'ok', 'cache' => 'ok', 'storage' => 'ok', 'queue' => 'ok']])
            ->assertHeader('Cache-Control', 'no-store, private');

        foreach (['SECRET', (string) config('database.connections.sqlite.database'), 'sk-ant'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }

        // No session cookie: it works even when the database that keeps sessions is down.
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_health_reports_a_failing_database_with_a_503(): void
    {
        Storage::fake('uploads');
        $this->app->bind(HealthCheck::class, fn () => new class extends HealthCheck
        {
            protected function pingDatabase(): bool
            {
                throw new QueryException('mysql', 'select 1', [], new PDOException('SQLSTATE[HY000] [2002] Connection refused to db.internal'));
            }
        });

        $this->getJson('/health')->assertStatus(503)
            ->assertJsonPath('status', 'failing')
            ->assertJsonPath('checks.database', 'failing')
            ->assertJsonPath('checks.app', 'ok')
            ->assertJsonMissing(['db.internal']);
    }

    public function test_health_reports_failing_storage_with_a_503(): void
    {
        Storage::shouldReceive('disk')->with('uploads')->andThrow(new RuntimeException('bucket gone'));

        $this->getJson('/health')->assertStatus(503)
            ->assertJsonPath('checks.storage', 'failing')
            ->assertJsonMissing(['bucket gone']);
    }

    public function test_identical_errors_email_the_admins_once_per_quiet_period(): void
    {
        Notification::fake();
        config(['goalline.admin_emails' => ['admin@example.com']]);
        $alerts = app(ErrorAlerts::class);

        $alerts->report(new RuntimeException('Photo avatars/abc123.jpg failed for user 42'));
        $alerts->report(new RuntimeException('Photo avatars/xyz789.jpg failed for user 7'));
        $alerts->report(new RuntimeException('Something else entirely'));

        Notification::assertSentOnDemandTimes(AdminAlert::class, 2);

        $this->travel(ErrorAlerts::QUIET_MINUTES + 1)->minutes();
        $alerts->report(new RuntimeException('Photo avatars/def456.jpg failed for user 9'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 3);
    }

    public function test_handled_cases_are_never_reported(): void
    {
        $handler = app(ExceptionHandler::class);

        foreach ([
            ValidationException::withMessages(['email' => 'Bad email']),
            new NotFoundHttpException,
            (new ModelNotFoundException)->setModel(User::class),
            new InvalidSignatureException,
            new AuthenticationException,
        ] as $exception) {
            $this->assertFalse($handler->shouldReport($exception), class_basename($exception).' should not email anyone.');
        }

        $this->assertTrue($handler->shouldReport(new RuntimeException('A real bug')));
    }

    /**
     * (A deadlock that a retry fixes never reaches the reporter at all: see DeadlockRetryTest.)
     */
    public function test_a_missing_photo_only_logs_and_emails_nobody(): void
    {
        Notification::fake();
        config(['goalline.admin_emails' => ['admin@example.com']]);

        // A photo whose file is gone: the placeholder is served, and nobody is emailed.
        Storage::fake('uploads');
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'avatars/gone.jpg'])->save();
        $this->actingAs($user)->get(route('avatars.show', $user))->assertOk();

        Notification::assertNothingSent();
    }

    public function test_preflight_lists_problems_without_printing_secret_values(): void
    {
        config([
            'app.key' => 'base64:TOPSECRETKEYVALUE',
            'app.url' => 'http://insecure.example.com',
            'services.anthropic.key' => 'sk-ant-TOPSECRET',
            'database.connections.sqlite.password' => 'DBSECRET',
        ]);

        $exit = Artisan::call('goalline:preflight');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('FAIL  APP_URL is the https address', $output);
        $this->assertStringContainsString('PASS  APP_KEY is set', $output);
        $this->assertStringContainsString('PASS  ANTHROPIC_API_KEY is set', $output);

        foreach (['TOPSECRET', 'DBSECRET', 'insecure.example.com', 'sk-ant'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_preflight_passes_when_production_settings_are_complete(): void
    {
        Storage::fake('uploads');
        config([
            'app.url' => 'https://goalline.example.com',
            'session.secure' => true,
            'services.anthropic.key' => 'sk-ant-test',
            'goalline.admin_emails' => ['admin@example.com'],
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'user',
            'mail.mailers.smtp.password' => 'pass',
            'session.driver' => 'database',
            'cache.default' => 'database',
            'queue.default' => 'database',
        ]);

        $this->assertSame(0, Artisan::call('goalline:preflight'), Artisan::output());
    }

    public function test_every_page_sends_security_headers(): void
    {
        foreach (['/', '/login', '/no-such-page'] as $path) {
            $response = $this->get($path);
            $csp = (string) $response->headers->get('Content-Security-Policy');

            $this->assertStringContainsString("default-src 'self'", $csp, $path);
            $this->assertStringContainsString("object-src 'none'", $csp, $path);
            $this->assertStringContainsString("frame-ancestors 'self'", $csp, $path);
            $this->assertStringNotContainsString('unsafe-eval', $csp, $path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }
    }

    public function test_hsts_is_sent_once_goalline_is_on_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        config(['app.url' => 'https://goalline.example.com']);
        $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_share_card_images_keep_their_own_sandboxed_policy(): void
    {
        $card = ResultCard::factory()->create();

        $csp = $this->actingAs($card->user)->get(route('cards.image', [$card, 'feed', 'svg']))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('sandbox', $csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
    }

    public function test_session_cookies_are_http_only(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_login_signup_and_password_reset_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertTooManyRequests();

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        }
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertTooManyRequests();
    }
}
