<?php

namespace Tests\Feature;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\AiFallback;
use App\Jobs\GenerateIntakeQuestions;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Services\AiCheck;
use App\Services\RoadmapPlanner;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * goalline:ai-check makes one tiny Claude call and says plainly whether it worked, never printing the key; and every
 * fall back to built-in questions or plans is logged as a warning with a counter. Tests never call the real API.
 */
class AiCheckTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-ant-api03-SUPERSECRETVALUE';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => self::KEY, 'services.anthropic.model' => 'claude-opus-5-5']);
    }

    private function apiError(int $status, string $type, string $message): APIStatusException
    {
        return APIStatusException::from(
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response($status, [], json_encode(['type' => 'error', 'error' => ['type' => $type, 'message' => $message]])),
        );
    }

    /**
     * The check with its one API call replaced.
     */
    private function checkThat(callable $send): void
    {
        $this->partialMock(AiCheck::class, fn ($mock) => $mock->shouldAllowMockingProtectedMethods()->shouldReceive('send')->andReturnUsing($send));
    }

    private function runCheck(): array
    {
        $exit = $this->withoutMockingConsoleOutput()->artisan('goalline:ai-check');
        $output = Artisan::output();
        $this->assertStringNotContainsString('SUPERSECRET', $output, 'The key is never printed.');

        return [$exit, $output];
    }

    public function test_a_working_key_passes(): void
    {
        $this->checkThat(fn () => (object) ['usage' => (object) ['inputTokens' => 14, 'outputTokens' => 3]]);

        [$exit, $output] = $this->runCheck();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('PASS  It works: claude-opus-5-5 answered with this key', $output);
    }

    public function test_a_rejected_key_fails_in_plain_english(): void
    {
        $this->checkThat(fn () => throw $this->apiError(401, 'authentication_error', 'invalid x-api-key '.self::KEY));

        [$exit, $output] = $this->runCheck();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('The API key was rejected (401)', $output);
    }

    public function test_a_wrong_model_a_rate_limit_and_no_network_each_say_what_to_do(): void
    {
        $this->checkThat(fn () => throw $this->apiError(404, 'not_found_error', 'model: claude-nope'));
        $this->assertStringContainsString("wasn't found (404). Check ANTHROPIC_MODEL", $this->runCheck()[1]);

        $this->checkThat(fn () => throw $this->apiError(429, 'rate_limit_error', 'slow down'));
        $this->assertStringContainsString('rate limited right now (429)', $this->runCheck()[1]);

        $this->checkThat(fn () => throw new APIConnectionException(request: new Request('POST', 'https://api.anthropic.com/v1/messages')));
        $this->assertStringContainsString("Couldn't reach the Claude API", $this->runCheck()[1]);

        $this->checkThat(fn () => throw $this->apiError(400, 'invalid_request_error', 'Your credit balance is too low. Key '.self::KEY));
        $output = $this->runCheck()[1];
        $this->assertStringContainsString('credit balance is too low', $output);
    }

    public function test_no_key_says_so_without_calling_the_api(): void
    {
        config(['services.anthropic.key' => '']);
        $this->checkThat(fn () => throw new RuntimeException('Should not be called.'));

        [$exit, $output] = $this->runCheck();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('No ANTHROPIC_API_KEY is set', $output);
    }

    public function test_falling_back_to_built_in_questions_is_a_warning_with_a_counter(): void
    {
        Log::spy();
        $goal = Goal::factory()->create();
        $intake = $goal->intake()->create();
        $this->partialMock(RoadmapPlanner::class, fn ($mock) => $mock->shouldReceive('intakeQuestions')->andThrow($this->apiError(401, 'authentication_error', 'invalid x-api-key')));

        (new GenerateIntakeQuestions($intake))->handle(app(RoadmapPlanner::class));
        (new GenerateIntakeQuestions($intake->fresh()))->handle(app(RoadmapPlanner::class));

        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $intake->fresh()->source);
        $this->assertSame(2, AiFallback::today()['questions']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, 'AI fallback: built-in questions')
            && $context['reason'] === 'The API key was rejected. Run php artisan goalline:ai-check.'
            && $context['count_today'] === 2);
    }

    public function test_fallback_reasons_never_include_the_key(): void
    {
        $this->assertSame('bad key sk-ant-… here', AiFallback::safe('bad key '.self::KEY.' here'));

        config(['services.anthropic.key' => 'plainsecretkey']);
        $this->assertSame('uses [the key] value', AiFallback::safe('uses plainsecretkey value'));
        Cache::flush();
    }
}
